<?php
declare(strict_types=1);

require_once __DIR__ . '/decimal.php';

class AetherValidationException extends InvalidArgumentException
{
    /** @var list<array{field: ?string, code: string, message: string}> */
    private array $errors;

    /**
     * @param list<string|array{field?: ?string, code?: string, message: string}> $errors
     */
    public function __construct(array $errors)
    {
        parent::__construct('Ongeldige invoer.');
        $this->errors = array_map(
            static function (string|array $error): array {
                if (is_string($error)) {
                    return ['field' => null, 'code' => 'invalid', 'message' => $error];
                }

                return [
                    'field' => isset($error['field']) ? (string) $error['field'] : null,
                    'code' => isset($error['code']) ? (string) $error['code'] : 'invalid',
                    'message' => (string) $error['message'],
                ];
            },
            $errors
        );
    }

    /** @return list<array{field: ?string, code: string, message: string}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return list<string> */
    public function getValidationErrors(): array
    {
        return array_column($this->errors, 'message');
    }
}

/**
 * @param array<string, mixed> $input
 * @param array<string, array<string, mixed>> $schema
 * @return array<string, mixed>
 */
function aetherValidateInput(array $input, array $schema): array
{
    /** @var list<array{field: ?string, code: string, message: string}> $errors */
    $errors = [];
    $addError = static function (string $field, string $code, string $message) use (&$errors): void {
        $errors[] = ['field' => $field, 'code' => $code, 'message' => $message];
    };

    $unknown = array_diff(array_keys($input), array_keys($schema));
    foreach ($unknown as $field) {
        $addError((string) $field, 'unknown_field', "Onverwacht veld: {$field}.");
    }

    $validated = [];
    foreach ($schema as $field => $rules) {
        $present = array_key_exists($field, $input);
        if (!$present) {
            if (($rules['required'] ?? false) === true) {
                $addError($field, 'required', "Veld {$field} is verplicht.");
            } elseif (array_key_exists('default', $rules)) {
                $validated[$field] = $rules['default'];
            }
            continue;
        }

        $value = $input[$field];
        $type = (string) ($rules['type'] ?? 'string');
        if ($value === null && str_starts_with($type, 'nullable_')) {
            $validated[$field] = null;
            continue;
        }

        if ($type === 'string' || $type === 'enum' || $type === 'nullable_enum' || $type === 'date') {
            if (!is_string($value)) {
                $addError($field, 'invalid_type', "Veld {$field} moet tekst zijn.");
                continue;
            }
            if (($rules['trim'] ?? $type !== 'date') === true) {
                $value = trim($value);
            }
            $length = function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
            if ($length < (int) ($rules['minLength'] ?? 0)) {
                $addError($field, 'too_short', "Veld {$field} is te kort.");
            }
            if (isset($rules['maxLength']) && $length > (int) $rules['maxLength']) {
                $addError($field, 'too_long', "Veld {$field} is te lang (maximaal {$rules['maxLength']} tekens).");
            }
            if (($type === 'enum' || $type === 'nullable_enum') && !in_array($value, $rules['values'] ?? [], true)) {
                $addError($field, 'invalid_enum', "Veld {$field} bevat geen toegestane waarde.");
            }
            if ($type === 'date') {
                if (($rules['allowEmpty'] ?? false) === true && $value === '') {
                    $validated[$field] = '';
                    continue;
                }
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                $dateErrors = DateTimeImmutable::getLastErrors();
                if ($date === false
                    || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))
                    || $date->format('Y-m-d') !== $value) {
                    $addError($field, 'invalid_date', "Veld {$field} moet een geldige datum in formaat JJJJ-MM-DD zijn.");
                }
            }
            $validated[$field] = $value;
            continue;
        }

        if ($type === 'int' || $type === 'nullable_int') {
            $validInteger = is_int($value) || (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1);
            if (!$validInteger || is_bool($value)) {
                $addError($field, 'invalid_type', "Veld {$field} moet een geheel getal zijn.");
                continue;
            }
            $value = (int) $value;
        } elseif ($type === 'decimal') {
            try {
                $value = aetherNormalizeDecimal($value, (int) ($rules['scale'] ?? 2));
            } catch (InvalidArgumentException $e) {
                $addError($field, 'invalid_decimal', "Veld {$field}: " . $e->getMessage());
                continue;
            }
            if (isset($rules['min']) && aetherDecimalCompare($value, (string) $rules['min'], (int) ($rules['scale'] ?? 2)) < 0) {
                $addError($field, 'below_minimum', "Veld {$field} is kleiner dan toegestaan.");
            }
            if (isset($rules['minExclusive']) && aetherDecimalCompare($value, (string) $rules['minExclusive'], (int) ($rules['scale'] ?? 2)) <= 0) {
                $addError($field, 'below_exclusive_minimum', "Veld {$field} moet groter zijn dan {$rules['minExclusive']}.");
            }
            if (isset($rules['max']) && aetherDecimalCompare($value, (string) $rules['max'], (int) ($rules['scale'] ?? 2)) > 0) {
                $addError($field, 'above_maximum', "Veld {$field} is groter dan toegestaan.");
            }
            $validated[$field] = $value;
            continue;
        } elseif ($type === 'number') {
            if ((!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) || is_bool($value)) {
                $addError($field, 'invalid_type', "Veld {$field} moet een getal zijn.");
                continue;
            }
            $value = (float) $value;
            if (!is_finite($value)) {
                $addError($field, 'not_finite', "Veld {$field} moet een eindig getal zijn.");
                continue;
            }
            if (isset($rules['scale'])) {
                $value = round($value, (int) $rules['scale']);
            }
        } elseif ($type === 'bool') {
            if (!is_bool($value)) {
                $addError($field, 'invalid_type', "Veld {$field} moet true of false zijn.");
                continue;
            }
        } else {
            throw new LogicException("Onbekend validatietype {$type} voor {$field}.");
        }

        if (isset($rules['min']) && $value < $rules['min']) {
            $addError($field, 'below_minimum', "Veld {$field} is kleiner dan toegestaan.");
        }
        if (isset($rules['minExclusive']) && $value <= $rules['minExclusive']) {
            $addError($field, 'below_exclusive_minimum', "Veld {$field} moet groter zijn dan {$rules['minExclusive']}.");
        }
        if (isset($rules['max']) && $value > $rules['max']) {
            $addError($field, 'above_maximum', "Veld {$field} is groter dan toegestaan.");
        }
        $validated[$field] = $value;
    }

    if ($errors !== []) {
        throw new AetherValidationException($errors);
    }

    return $validated;
}
