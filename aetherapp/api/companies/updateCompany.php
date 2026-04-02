<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/companyUtils.php';

$rawInput = file_get_contents('php://input');
$postData = json_decode($rawInput, true) ?? [];

$id = (int) ($postData['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen geldig bedrijf ID.']);
    exit;
}

unset($postData['id']);

if (empty($postData)) {
    http_response_code(400);
    echo json_encode(['error' => 'Geen velden om bij te werken.']);
    exit;
}

try {
    $pdo = getPDO();
    requirePrivilegedCompanyAccess($pdo);

    $currentCompany = dbOne(
        $pdo,
        'SELECT id
           FROM tblCompany
          WHERE id = :id',
        ['id' => $id]
    );

    if ($currentCompany === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Bedrijf niet gevonden.']);
        exit;
    }

    $allowedFields = ['companyName', 'description', 'foundationDate', 'companyValue', 'stability', 'profitability'];
    $updateData = [];

    foreach ($allowedFields as $field) {
        if (!array_key_exists($field, $postData)) {
            continue;
        }

        $value = $postData[$field];

        switch ($field) {
            case 'companyName':
                $value = trim((string) $value);
                if ($value === '') {
                    http_response_code(400);
                    echo json_encode(['error' => 'De bedrijfsnaam is verplicht.']);
                    exit;
                }
                break;

            case 'description':
                $value = trim((string) $value);
                break;

            case 'foundationDate':
                $value = trim((string) $value);
                if ($value === '') {
                    $value = null;
                    break;
                }

                $dateTime = DateTimeImmutable::createFromFormat('Y-m-d', $value);
                $dateErrors = DateTimeImmutable::getLastErrors();
                $hasDateErrors = is_array($dateErrors)
                    && (($dateErrors['warning_count'] ?? 0) > 0 || ($dateErrors['error_count'] ?? 0) > 0);

                if (!$dateTime || $hasDateErrors || $dateTime->format('Y-m-d') !== $value) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Geef een geldige oprichtingsdatum op.']);
                    exit;
                }
                break;

            case 'companyValue':
                $value = normalizeCompanyValue($value);
                break;

            case 'stability':
            case 'profitability':
                $value = normalizeCompanySliderValue($value);
                break;
        }

        $updateData[$field] = $value;
    }

    if (empty($updateData)) {
        http_response_code(400);
        echo json_encode(['error' => 'Geen geldige velden om bij te werken.']);
        exit;
    }

    $setParts = [];
    foreach (array_keys($updateData) as $column) {
        $setParts[] = "$column = :$column";
    }
    $setParts[] = 'updatedAt = CURRENT_TIMESTAMP';

    $sql = 'UPDATE tblCompany
               SET ' . implode(', ', $setParts) . '
             WHERE id = :id';

    $updateData['id'] = $id;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($updateData);

    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Kon bedrijf niet bewaren.']);
}
