<?php
declare(strict_types=1);

require_once __DIR__ . '/companyUtils.php';
require_once __DIR__ . '/companyRepository.php';
require_once __DIR__ . '/companyPersonnelRepository.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../characters/companyShareUtils.php';
require_once __DIR__ . '/../shares/shareAccess.php';
require_once __DIR__ . '/companyLeadershipRules.php';

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
    if (array_key_exists('companyValue', $fields) && aetherShareLegacyWritesClosed($pdo)) {
        throw new AetherCompanyException(409, 'Bedrijfswaarde moet via de nieuwe aandelenservice worden gewijzigd.');
    }
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
    if (array_key_exists('foundationDate', $fields) && aetherCompanySnapshotHistoryAvailable($pdo)) {
        $stmt = $pdo->prepare('UPDATE tblCompanyFoundationSnapshot
            SET foundationDate = :foundationDate WHERE idCompany = :idCompany');
        $stmt->execute(['foundationDate' => $fields['foundationDate'], 'idCompany' => $idCompany]);
    }
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
    if (!aetherCompanyLeadershipAvailable($pdo)) {
        throw new AetherCompanyException(409, 'Voer eerst de fase-5-personeelsmigratie uit.');
    }

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
    $suppliedFlags = [];
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
        $isLeader = aetherCharacterHasCompanyLeaderProfession($pdo, $idCharacter);
        $suppliedFlags[$idCharacter] = [
            'board' => array_key_exists('isBoardMember', $entry),
        ];
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
            'functionDescription' => $entry['functionDescription'],
            'salaryIncreasePercentage' => $entry['salaryIncreasePercentage'],
            'skills' => $skills,
            'isBoardMember' => $isLeader || (bool) ($entry['isBoardMember'] ?? false),
            'isCompanyLeader' => $isLeader,
        ];
    }

    // Older clients may omit the board flag. The leader flag is always derived.
    $boardFlags = [];
    if (aetherShareLegacyWritesClosed($pdo)) {
        $stmt = $pdo->prepare('SELECT idCharacter, isBoardMember FROM tblCompanyPersonnel WHERE idCompany = :idCompany');
        $stmt->execute(['idCompany' => $idCompany]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $boardFlags[(int) $row['idCharacter']] = $row;
        }
    }
    foreach ($normalized as &$entry) {
        $old = $boardFlags[(int) $entry['idCharacter']] ?? null;
        if ($old !== null && !$suppliedFlags[(int) $entry['idCharacter']]['board']) {
            $entry['isBoardMember'] = (int) $old['isBoardMember'] === 1;
        }
        if ($entry['isCompanyLeader']) {
            $entry['isBoardMember'] = true;
        }
    }
    unset($entry);
    $leaderOtherCompany = $pdo->prepare('SELECT 1 FROM tblCompanyPersonnel
        WHERE idCharacter = :idCharacter AND idCompany <> :idCompany LIMIT 1');
    foreach ($normalized as $entry) {
        if (!$entry['isCompanyLeader']) continue;
        $classStmt = $pdo->prepare('SELECT `class` FROM tblCharacter WHERE id = :idCharacter');
        $classStmt->execute(['idCharacter' => $entry['idCharacter']]);
        if ($classStmt->fetchColumn() !== 'middle class') {
            throw new AetherValidationException(['Een bedrijfsleider moet een middenklassepersonage zijn.']);
        }
        $leaderOtherCompany->execute(['idCharacter' => $entry['idCharacter'], 'idCompany' => $idCompany]);
        if ($leaderOtherCompany->fetchColumn() !== false) {
            throw new AetherValidationException(['Dit personage leidt al een ander bedrijf.']);
        }
    }
    aetherPersonnelDeleteCompanyLinks($pdo, $idCompany);
    foreach ($normalized as $entry) {
        $idPersonnel = aetherPersonnelInsert($pdo, $idCompany, $entry);
        $stmt = $pdo->prepare('UPDATE tblCompanyPersonnel SET isBoardMember = :isBoardMember,
                                 isCompanyLeader = :isCompanyLeader, importance = :importance WHERE id = :id');
        $stmt->execute(['id' => $idPersonnel, 'isBoardMember' => (int) $entry['isBoardMember'],
                        'isCompanyLeader' => (int) $entry['isCompanyLeader'], 'importance' => $entry['importance']]);
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
