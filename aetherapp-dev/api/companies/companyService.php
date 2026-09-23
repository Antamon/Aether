<?php
declare(strict_types=1);

require_once __DIR__ . '/companyUtils.php';
require_once __DIR__ . '/companyRepository.php';
require_once __DIR__ . '/companyPersonnelRepository.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../characters/companyShareUtils.php';

final class AetherCompanyException extends RuntimeException
{
    public function __construct(private int $httpStatus, string $message) { parent::__construct($message); }
    public function getHttpStatus(): int { return $this->httpStatus; }
}

function aetherCompanyRequireExisting(PDO $pdo, int $idCompany, bool $lock = false): void
{
    if (!aetherCompanyExists($pdo, $idCompany, $lock)) {
        throw new AetherCompanyException(404, 'Bedrijf niet gevonden.');
    }
}

function aetherCreateCompany(PDO $pdo, string $name): array
{
    $foundationDate = getDefaultCompanyFoundationDate();
    $id = aetherCompanyInsert($pdo, $name, $foundationDate);
    return [
        'id' => $id, 'companyName' => $name, 'description' => '',
        'foundationDate' => $foundationDate, 'companyValue' => 0,
        'stability' => 0, 'profitability' => 0,
    ];
}

function aetherUpdateCompany(PDO $pdo, int $idCompany, array $fields): array
{
    if (isset($fields['description']) && strlen($fields['description']) > 65535) {
        throw new AetherValidationException(['De bedrijfsbeschrijving is te lang voor het tekstveld.']);
    }
    $current = aetherCompanyLockForUpdate($pdo, $idCompany);
    if ($current === null) throw new AetherCompanyException(404, 'Bedrijf niet gevonden.');
    if (array_key_exists('foundationDate', $fields) && $fields['foundationDate'] === '') {
        $fields['foundationDate'] = null;
    }
    $oldType = getCompanyTypeByValue($current['companyValue'] ?? 0)['key'] ?? null;
    $newValue = $fields['companyValue'] ?? ($current['companyValue'] ?? 0);
    $newType = getCompanyTypeByValue($newValue)['key'] ?? null;
    aetherCompanyUpdateFields($pdo, $idCompany, $fields);
    $updatedShareTraitCount = 0;
    if (array_key_exists('companyValue', $fields) && $oldType !== null && $newType !== null && $oldType !== $newType) {
        $updatedShareTraitCount = remapCompanyShareTraitsForCompany($pdo, $idCompany, $newType);
    }
    return ['status' => 'ok', 'updatedShareTraitCount' => $updatedShareTraitCount];
}

/** Called inside aetherRunIdempotentMutation's transaction, after its claim. */
function aetherSaveCompanyPersonnel(PDO $pdo, array $input): array
{
    $idCompany = (int) $input['idCompany'];
    aetherCompanyRequireExisting($pdo, $idCompany, true);

    // Check every referenced object and relationship before changing any domain row.
    // Lock characters in ascending order. Character deletion locks the row before cleanup.
    $characterIds = array_values(array_unique(array_filter(array_map(
        static fn(array $entry): int => (int) $entry['idCharacter'], $input['personnel']
    ), static fn(int $id): bool => $id > 0)));
    sort($characterIds, SORT_NUMERIC);
    $availableCharacters = [];
    foreach ($characterIds as $id) $availableCharacters[$id] = aetherPersonnelCharacterExists($pdo, $id);
    $seenCharacters = [];
    $normalized = [];
    foreach ($input['personnel'] as $entry) {
        $idCharacter = (int) $entry['idCharacter'];
        if ($idCharacter === 0) continue; // UI placeholder, as in the previous route.
        if (isset($seenCharacters[$idCharacter])) {
            throw new AetherValidationException(['Een personage kan maar één keer aan hetzelfde bedrijf gekoppeld worden.']);
        }
        $seenCharacters[$idCharacter] = true;
        if (!$availableCharacters[$idCharacter]) {
            throw new AetherValidationException(['Een gekozen personage bestaat niet of staat nog in draft.']);
        }
        $skills = [];
        foreach ($entry['skills'] as $skill) {
            $idSkill = (int) $skill['idSkill'];
            if ($idSkill === 0) continue; // UI placeholder.
            if ($skills !== []) {
                throw new AetherValidationException(['Per personeelslid kan maar één vaardigheid gekoppeld worden.']);
            }
            if (!aetherPersonnelSkillExists($pdo, $idSkill)) {
                throw new AetherValidationException(['Een gekozen vaardigheid bestaat niet.']);
            }
            $resolved = [];
            $seenSpecialisations = [];
            foreach ($skill['specialisations'] as $specialisation) {
                $id = (int) $specialisation['idSkillSpecialisation'];
                if ($id > 0) {
                    if (aetherPersonnelSpecialisationForSkill($pdo, $id, $idSkill) === null) {
                        throw new AetherValidationException(['De gekozen specialisatie hoort niet bij deze vaardigheid.']);
                    }
                    $key = 'id:' . $id;
                    $value = ['id' => $id];
                } else {
                    $name = (string) $specialisation['name'];
                    $existingId = aetherPersonnelSpecialisationByName($pdo, $idSkill, $name);
                    $key = $existingId !== null ? 'id:' . $existingId : 'name:' . (function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name));
                    $value = $existingId !== null ? ['id' => $existingId] : ['name' => $name];
                }
                if (isset($seenSpecialisations[$key])) continue;
                $seenSpecialisations[$key] = true;
                $resolved[] = $value;
            }
            $skills[] = ['idSkill' => $idSkill, 'level' => (int) $skill['level'], 'specialisations' => $resolved];
        }
        $normalized[] = [
            'idCharacter' => $idCharacter,
            'importance' => $entry['importance'],
            'salaryIncreasePercentage' => $entry['salaryIncreasePercentage'],
            'skills' => $skills,
        ];
    }

    aetherPersonnelDeleteCompanyLinks($pdo, $idCompany);
    foreach ($normalized as $entry) {
        $idPersonnel = aetherPersonnelInsert($pdo, $idCompany, $entry);
        foreach ($entry['skills'] as $skill) {
            $idPersonnelSkill = aetherPersonnelInsertSkill($pdo, $idPersonnel, $skill);
            foreach ($skill['specialisations'] as $specialisation) {
                $id = $specialisation['id'] ?? (
                    aetherPersonnelSpecialisationByName($pdo, $skill['idSkill'], $specialisation['name'])
                    ?? aetherPersonnelInsertSpecialisation($pdo, $skill['idSkill'], $specialisation['name'])
                );
                aetherPersonnelInsertSkillSpecialisation($pdo, $idPersonnelSkill, (int) $id);
            }
        }
    }
    refreshCompanySnapshotsForCurrentPersonnel($pdo, $idCompany);
    return [
        'success' => true,
        'personnelEntries' => getCompanyPersonnelEntries($pdo, $idCompany, true),
        'snapshots' => getCompanySnapshots($pdo, $idCompany, true),
    ];
}
