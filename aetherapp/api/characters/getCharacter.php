<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/traitUtils.php';
require_once __DIR__ . '/economyUtils.php';
require_once __DIR__ . '/characterMediaUtils.php';
require_once __DIR__ . '/characterLanguageUtils.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $idCharacter = isset($input['id']) ? (int) $input['id'] : 0;

    if ($idCharacter <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Ongeldig character-id.']);
        exit;
    }

    $pdo = getPDO();
    $currentUserRole = getCurrentUserRole($pdo);

    // --- 1. Basisgegevens van het personage ---
    $stmt = $pdo->prepare('SELECT * FROM tblCharacter WHERE id = ?');
    $stmt->execute([$idCharacter]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        http_response_code(404);
        echo json_encode(['error' => 'Personage niet gevonden.']);
        exit;
    }

    // --- 2. Skills (zonder types, die doen we apart) ---
    $sqlSkills = "
        SELECT
            lcs.idSkill      AS id,
            s.name,
            s.description,
            s.beginner,
            s.professional,
            s.master,
            lcs.level
        FROM tblLinkCharacterSkill AS lcs
        JOIN tblSkill AS s
              ON s.id = lcs.idSkill
        WHERE lcs.idCharacter = ?
        ORDER BY s.name
    ";
    $stmt = $pdo->prepare($sqlSkills);
    $stmt->execute([$idCharacter]);
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. Specialisaties per skill voor dit personage ---
    $sqlSpecs = "
        SELECT
        cs.idSkill,
        ss.id,
        ss.name,
        ss.kind
        FROM tblCharacterSpecialisation AS cs
        JOIN tblSkillSpecialisation AS ss
            ON ss.id = cs.idSkillSpecialisation
        WHERE cs.idCharacter = ?
        ORDER BY ss.name
    ";
    $stmt = $pdo->prepare($sqlSpecs);
    $stmt->execute([$idCharacter]);
    $specRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $specBySkill = [];
    foreach ($specRows as $row) {
        $skillId = (int) $row['idSkill'];
        if (!isset($specBySkill[$skillId])) {
            $specBySkill[$skillId] = [];
        }
        $specBySkill[$skillId][] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'kind' => $row['kind'] ?? 'specialisation',
        ];
    }

    // --- 4. Type-attributen per skill (met beschrijving) ---
    $sqlTypes = "
        SELECT
            lst.idSkill,
            st.name,
            st.description
        FROM tblLinkSkillType AS lst
        JOIN tblSkillType AS st
              ON st.id = lst.idSkillType
        WHERE lst.idSkill IN (
            SELECT idSkill
            FROM tblLinkCharacterSkill
            WHERE idCharacter = ?
        )
        ORDER BY st.name
    ";
    $stmt = $pdo->prepare($sqlTypes);
    $stmt->execute([$idCharacter]);
    $typeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $typesBySkill = [];
    foreach ($typeRows as $row) {
        $skillId = (int) $row['idSkill'];
        if (!isset($typesBySkill[$skillId])) {
            $typesBySkill[$skillId] = [];
        }
        $typesBySkill[$skillId][] = [
            'name' => $row['name'],
            'description' => $row['description'],
        ];
    }

    // --- 5. Skills verrijken met types + specialisations ---
    foreach ($skills as &$skill) {
        $sid = (int) $skill['id'];

        // array van {name, description}
        $skill['types'] = $typesBySkill[$sid] ?? [];

        // bestaande specialisaties
        $skill['specialisations'] = $specBySkill[$sid] ?? [];
    }
    unset($skill);

    $character['skills'] = $skills;

    // --- 6. Naam van de deelnemer (indien gekoppeld) ---
    if (!empty($character['idUser'])) {
        $stmt = $pdo->prepare('SELECT firstName, lastName FROM tblUser WHERE id = ?');
        $stmt->execute([(int) $character['idUser']]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userRow) {
            $character['nameParticipant'] = trim($userRow['firstName'] . ' ' . $userRow['lastName']);
        } else {
            $character['nameParticipant'] = null;
        }
    } else {
        $character['nameParticipant'] = null;
    }

    // --- 7. Traits per group voor deze character class ---
    $character['traitGroups'] = buildCharacterTraitGroups(
        $pdo,
        $idCharacter,
        (string) ($character['class'] ?? ''),
        ['status', 'quality']
    );
    $character['professionGroups'] = buildCharacterTraitGroups(
        $pdo,
        $idCharacter,
        (string) ($character['class'] ?? ''),
        ['profession']
    );

    // --- 8. Puntentotalen voor skills en traits ---
    $pointSummary = getCharacterPointSummary($pdo, $character);
    $character['experience'] = $pointSummary['experienceBudget'];
    $character['maxExperience'] = $pointSummary['totalExperience'];
    $character['experienceToTrait'] = $pointSummary['experienceToTrait'];
    $character['baseStatusPoints'] = $pointSummary['baseStatusPoints'];
    $character['usedStatusPoints'] = $pointSummary['usedStatusPoints'];
    $character['maxStatusPoints'] = $pointSummary['maxStatusPoints'];
    $character['availableStatusPoints'] = getVisibleAvailableStatusPoints($pointSummary, $currentUserRole);
    $languageSummary = getCharacterLanguageSummary($pdo, $character, $skills, $pointSummary);
    $character['languages'] = $languageSummary['languages'];
    $character['languageSummary'] = $languageSummary;
    $currentUserId = getCurrentUserId();
    $canViewEconomy = canViewCharacterEconomy($character, $currentUserRole, $currentUserId);
    $character['canManageLanguages'] = $languageSummary['isVisible']
        && canCurrentUserManageCharacterLanguages($character, $currentUserRole, $currentUserId);
    $character['portraitUrl'] = getCharacterPortraitUrl($idCharacter);
    $character['canManagePortrait'] = canManageCharacterPortrait($character, $currentUserRole, $currentUserId);
    $character['canEditBankAccount'] = canEditCharacterBankAccount($character, $currentUserRole);
    $character['canTransferMoney'] = canTransferFromCharacter($character, $currentUserRole);
    $character['canDeleteBankTransactions'] = isPrivilegedUserRole($currentUserRole);
    $character['canManageSecurities'] = canManageCharacterSecurities($character, $currentUserRole, $currentUserId);
    $character['canApproveSecuritiesSnapshots'] = canApproveCharacterSecuritiesSnapshots($character, $currentUserRole, $currentUserId);
    $character['defaultBankTransferDate'] = getDefaultBankTransferDate();
    $character['bankTransferTargets'] = $character['canTransferMoney']
        ? getBankTransferTargets($pdo, $idCharacter)
        : [];
    $recurringIncomeBreakdown = getCharacterRecurringIncomeBreakdown($pdo, $character);
    $character['baseRecurringIncome'] = $recurringIncomeBreakdown['baseRecurringIncome'];
    $character['salaryIncreaseBaseIncome'] = $recurringIncomeBreakdown['salaryIncreaseBaseIncome'];
    $character['salaryIncreasePercentage'] = $recurringIncomeBreakdown['salaryIncreasePercentage'];
    $character['salaryIncreaseAmount'] = $recurringIncomeBreakdown['salaryIncreaseAmount'];
    $character['grossRecurringIncome'] = $recurringIncomeBreakdown['grossRecurringIncome'] ?? $character['baseRecurringIncome'];
    $character['householdStaffExpenseAmount'] = $recurringIncomeBreakdown['householdStaffExpenseAmount'] ?? 0;
    $character['recurringIncomeTotal'] = $recurringIncomeBreakdown['totalRecurringIncome'];
    $character['middleClassLivingStandardIncome'] = getCharacterMiddleClassLivingStandardIncome($pdo, $character);
    $character['virtualCompanyShareLivingStandardIncome'] = getCharacterVirtualCompanyShareLivingStandardIncome($pdo, $idCharacter);
    $character['draftBankAccountAmount'] = getDraftBankAccountAmountForCharacter($pdo, $character);
    $securitiesPortfolio = getCharacterSecuritiesPortfolio($pdo, $character);
    $character['securitiesaccount'] = $securitiesPortfolio['balance'];
    $character['securitiesManagerType'] = $securitiesPortfolio['managerType'];
    $character['securitiesManagerCharacterId'] = $securitiesPortfolio['managerCharacterId'];
    $character['securitiesRiskProfile'] = $securitiesPortfolio['riskProfile'];
    $character['securitiesManagerSkillLevel'] = $securitiesPortfolio['managerSkillLevel'];
    $character['securitiesRiskProfileOptions'] = getCharacterSecuritiesRiskProfileOptions();
    $character['securitiesManagerOptions'] = $character['canManageSecurities']
        ? getCharacterSecuritiesManagerOptions($pdo, $idCharacter)
        : [];
    $character['bankTransactions'] = $canViewEconomy
        ? getCharacterBankTransactions($pdo, $idCharacter)
        : [];
    $character['canCreateEconomySnapshots'] = canManageCharacterEconomySnapshots($character, $currentUserRole, $currentUserId);
    $character['economySnapshotEventOptions'] = $character['canCreateEconomySnapshots']
        ? getCharacterEconomySnapshotEventOptions($pdo, $idCharacter)
        : [];
    $character['economySnapshots'] = $canViewEconomy
        ? getCharacterEconomySnapshots($pdo, $idCharacter, $character['canApproveSecuritiesSnapshots'])
        : [];
    $character['companyShares'] = $canViewEconomy
        ? getCharacterCompanyShares($pdo, $character, $currentUserRole, $currentUserId)
        : [];
    $character['companySharePurchaseOptions'] = $canViewEconomy
        ? getCharacterCompanySharePurchaseOptions($pdo, $character, $currentUserRole, $currentUserId)
        : [];

    echo json_encode($character);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Kon character niet ophalen.',
        'details' => $e->getMessage()
    ]);
}
