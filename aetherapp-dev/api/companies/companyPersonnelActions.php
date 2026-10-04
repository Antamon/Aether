<?php
declare(strict_types=1);

require_once __DIR__ . '/companyService.php';
require_once __DIR__ . '/companySchemas.php';
require_once __DIR__ . '/../shared/idempotency.php';

function aetherCompanyPersonnelActionInput(array $input): array
{
    $schema = [
        'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
        'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
        'action' => ['type' => 'enum', 'required' => true, 'values' => ['add', 'update', 'remove']],
        'salaryIncreasePercentage' => ['type' => 'decimal', 'scale' => 2, 'min' => '0.00', 'max' => '999999.99'],
        'isBoardMember' => ['type' => 'bool'],
    ];
    $out = aetherValidateInput($input, $schema);
    if ($out['action'] === 'remove' && (isset($out['salaryIncreasePercentage']) || isset($out['isBoardMember']))) {
        throw new AetherValidationException(['Bij verwijderen zijn geen personeelsvelden toegestaan.']);
    }
    return $out;
}

function aetherCompanyPersonnelActorCanEdit(PDO $pdo, array $user, int $idCompany): bool
{
    if (aetherIsPrivilegedRole((string) $user['role'])) return true;
    if ((string) $user['role'] !== 'participant' || !aetherCompanyLeadershipAvailable($pdo)) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM tblCompanyPersonnel AS cp
        JOIN tblCharacter AS c ON c.id = cp.idCharacter
        WHERE cp.idCompany = :idCompany AND c.idUser = :idUser
          AND EXISTS (SELECT 1 FROM tblLinkCharacterTrait AS link
                      JOIN tblTrait AS trait ON trait.id = link.idTrait
                      WHERE link.idCharacter = c.id AND trait.`class` = 'middle class'
                        AND trait.`type` = 'profession'
                        AND trait.name IN ('Bedrijfsleider', 'Bedrijfsleider klein', 'Bedrijfsleider groot'))
        LIMIT 1");
    $stmt->execute(['idCompany' => $idCompany, 'idUser' => (int) $user['id']]);
    return $stmt->fetchColumn() !== false;
}

function aetherCompanyPersonnelAction(PDO $pdo, array $user, array $input): array
{
    $idCompany = $input['idCompany'];
    $idCharacter = $input['idCharacter'];
    aetherCompanyRequireExisting($pdo, $idCompany, true);
    if (!aetherCompanyLeadershipAvailable($pdo)) {
        throw new AetherCompanyException(409, 'Voer eerst de fase-5-personeelsmigratie uit.');
    }
    if (!aetherCompanyPersonnelActorCanEdit($pdo, $user, $idCompany)) {
        throw new AetherCompanyException(403, 'Je hebt geen rechten om dit bedrijfspersoneel te beheren.');
    }
    if ($input['action'] === 'add' && !aetherPersonnelCharacterExists($pdo, $idCharacter)) {
        throw new AetherCompanyException(404, 'Personage niet gevonden of nog in draft.');
    }
    $stmt = $pdo->prepare('SELECT id, isCompanyLeader, isBoardMember, salaryIncreasePercentage
        FROM tblCompanyPersonnel WHERE idCompany = :idCompany AND idCharacter = :idCharacter FOR UPDATE');
    $stmt->execute(['idCompany' => $idCompany, 'idCharacter' => $idCharacter]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $action = $input['action'];
    if ($action === 'add') {
        if ($current !== null) throw new AetherCompanyException(409, 'Dit personage werkt al bij het bedrijf.');
        $isLeader = aetherCharacterHasCompanyLeaderProfession($pdo, $idCharacter);
        if ($isLeader) {
            $stmt = $pdo->prepare('SELECT 1 FROM tblCompanyPersonnel WHERE idCharacter = :idCharacter LIMIT 1');
            $stmt->execute(['idCharacter' => $idCharacter]);
            if ($stmt->fetchColumn() !== false) throw new AetherCompanyException(409, 'Dit personage leidt al een ander bedrijf.');
        }
        $stmt = $pdo->prepare('INSERT INTO tblCompanyPersonnel
            (idCompany, idCharacter, importance, salaryIncreasePercentage, isBoardMember, isCompanyLeader)
            VALUES (:idCompany, :idCharacter, :importance, :salary, :board, :leader)');
        $stmt->execute(['idCompany' => $idCompany, 'idCharacter' => $idCharacter,
            'importance' => 'Moderate', 'salary' => $input['salaryIncreasePercentage'] ?? 0,
            'board' => (int) ($isLeader || ($input['isBoardMember'] ?? false)), 'leader' => (int) $isLeader]);
    } elseif ($action === 'update') {
        if ($current === null) throw new AetherCompanyException(404, 'Personeelslid niet gevonden.');
        $isLeader = aetherCharacterHasCompanyLeaderProfession($pdo, $idCharacter);
        $board = $isLeader ? 1 : (int) ($input['isBoardMember'] ?? ((int) $current['isBoardMember'] === 1));
        $stmt = $pdo->prepare('UPDATE tblCompanyPersonnel
            SET salaryIncreasePercentage = :salary, isBoardMember = :board, isCompanyLeader = :leader
            WHERE id = :id');
        $stmt->execute(['salary' => $input['salaryIncreasePercentage'] ?? $current['salaryIncreasePercentage'],
            'board' => $board, 'leader' => (int) $isLeader, 'id' => $current['id']]);
    } else {
        if ($current === null) throw new AetherCompanyException(404, 'Personeelslid niet gevonden.');
        if (aetherCharacterHasCompanyLeaderProfession($pdo, $idCharacter) && !aetherIsPrivilegedRole((string) $user['role'])) {
            throw new AetherCompanyException(403, 'Een bedrijfsleider kan de eigen leiding niet verwijderen.');
        }
        $stmt = $pdo->prepare('DELETE cpss FROM tblCompanyPersonnelSkillSpecialisation AS cpss
            JOIN tblCompanyPersonnelSkill AS cps ON cps.id = cpss.idCompanyPersonnelSkill
            WHERE cps.idCompanyPersonnel = :id');
        $stmt->execute(['id' => $current['id']]);
        $stmt = $pdo->prepare('DELETE FROM tblCompanyPersonnelSkill WHERE idCompanyPersonnel = :id');
        $stmt->execute(['id' => $current['id']]);
        $stmt = $pdo->prepare('DELETE FROM tblCompanyPersonnel WHERE id = :id');
        $stmt->execute(['id' => $current['id']]);
    }
    refreshCompanySnapshotsForCurrentPersonnel($pdo, $idCompany);
    return aetherCompanyPersonnelLimitedView($pdo, $idCompany);
}

function aetherCompanyPersonnelLimitedView(PDO $pdo, int $idCompany): array
{
    $stmt = $pdo->prepare('SELECT cp.idCharacter, cp.salaryIncreasePercentage,
        cp.isBoardMember, cp.isCompanyLeader, c.firstName, c.lastName, c.`class`,
        co.companyValue
        FROM tblCompanyPersonnel AS cp
        JOIN tblCharacter AS c ON c.id = cp.idCharacter
        JOIN tblCompany AS co ON co.id = cp.idCompany
        WHERE cp.idCompany = :idCompany ORDER BY c.lastName, c.firstName, cp.id');
    $stmt->execute(['idCompany' => $idCompany]);
    $entries = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $isLeader = aetherCharacterHasCompanyLeaderProfession($pdo, (int) $row['idCharacter']);
        $monthlyIncrease = getCharacterCompanySalaryIncreaseAmount($pdo,
            ['id' => (int) $row['idCharacter'], 'class' => $row['class']], $idCompany);
        $entries[] = [
            'idCharacter' => (int) $row['idCharacter'],
            'name' => trim($row['firstName'] . ' ' . $row['lastName']),
            'salaryIncreasePercentage' => (float) $row['salaryIncreasePercentage'],
            'monthlySalaryIncreaseAmount' => $monthlyIncrease,
            'annualProfitPressureAmount' => round(12 * $monthlyIncrease, 2),
            'isBoardMember' => (int) $row['isBoardMember'] === 1,
            'isCompanyLeader' => $isLeader,
            'leaderMonthlyIncome' => $isLeader
                ? (float) aetherCompanyLeaderMonthlyIncome($row['companyValue']) : 0,
            'leaderStatusPoints' => $isLeader
                ? aetherCompanyLeaderTier($row['companyValue'])['statusPoints'] : 0,
            'leaderPersonnelRequirement' => $isLeader
                ? aetherCompanyLeaderTier($row['companyValue'])['personnelRequirement'] : 0,
        ];
    }
    $candidates = $pdo->query("SELECT id, firstName, lastName FROM tblCharacter
                                 WHERE `state` <> 'draft' ORDER BY lastName, firstName, id")
        ->fetchAll(PDO::FETCH_ASSOC);
    return ['success' => true, 'idCompany' => $idCompany, 'personnel' => $entries,
        'characterOptions' => array_map(static fn(array $row): array => [
            'idCharacter' => (int) $row['id'],
            'name' => trim($row['firstName'] . ' ' . $row['lastName']),
        ], $candidates)];
}
