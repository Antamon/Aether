<?php
declare(strict_types=1);

require_once __DIR__ . '/characterPointUtils.php';
require_once __DIR__ . '/traitUtils.php';
require_once __DIR__ . '/economyUtils.php';
require_once __DIR__ . '/characterMediaUtils.php';
require_once __DIR__ . '/characterLanguageUtils.php';
require_once __DIR__ . '/characterRichText.php';
require_once __DIR__ . '/characterReadRepository.php';

/**
 * Build the established getCharacter response without changing its field order.
 *
 * @param array<string, mixed> $currentUser
 * @param array<string, mixed> $character
 * @return array<string, mixed>
 */
function aetherBuildCharacterReadModel(PDO $pdo, array $currentUser, array $character): array
{
    $characterId = (int) $character['id'];
    $currentUserRole = (string) $currentUser['role'];
    $currentUserId = (int) $currentUser['id'];

    $skills = aetherFetchCharacterReadSkills(
        $pdo,
        $characterId,
        aetherIsPrivilegedRole($currentUserRole)
    );

    $specialisationsBySkill = [];
    foreach (aetherFetchCharacterReadSpecialisations($pdo, $characterId) as $row) {
        $skillId = (int) $row['idSkill'];
        $specialisationsBySkill[$skillId] ??= [];
        $specialisationsBySkill[$skillId][] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'kind' => $row['kind'] ?? 'specialisation',
        ];
    }

    $typesBySkill = [];
    foreach (aetherFetchCharacterReadSkillTypes($pdo, $characterId) as $row) {
        $skillId = (int) $row['idSkill'];
        $typesBySkill[$skillId] ??= [];
        $typesBySkill[$skillId][] = [
            'idSkillType' => (int) ($row['idSkillType'] ?? 0),
            'code' => (string) ($row['code'] ?? ''),
            'name' => $row['name'],
            'description' => $row['description'],
        ];
    }

    foreach ($skills as &$skill) {
        $skillId = (int) $skill['id'];
        $skill['types'] = $typesBySkill[$skillId] ?? [];
        $skill['specialisations'] = $specialisationsBySkill[$skillId] ?? [];
    }
    unset($skill);

    $character['skills'] = $skills;
    $character['nameParticipant'] = !empty($character['idUser'])
        ? aetherFetchCharacterParticipantName($pdo, (int) $character['idUser'])
        : null;

    $character['traitGroups'] = buildCharacterTraitGroups(
        $pdo,
        $characterId,
        (string) ($character['class'] ?? ''),
        ['status', 'quality']
    );
    $character['professionGroups'] = buildCharacterTraitGroups(
        $pdo,
        $characterId,
        (string) ($character['class'] ?? ''),
        ['profession']
    );

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
    $canViewEconomy = canViewCharacterEconomy($character, $currentUserRole, $currentUserId);
    $character['canManageLanguages'] = $languageSummary['isVisible']
        && canCurrentUserManageCharacterLanguages($character, $currentUserRole, $currentUserId);
    $character['portraitUrl'] = getCharacterPortraitUrl($characterId);
    $character['canManagePortrait'] = canManageCharacterPortrait($character, $currentUserRole, $currentUserId);
    $character['canEditBankAccount'] = canEditCharacterBankAccount($character, $currentUserRole);
    $character['canEditSecuritiesAccount'] = canEditCharacterSecuritiesAccount($character, $currentUserRole);
    $character['canTransferMoney'] = canTransferFromCharacter($character, $currentUserRole);
    $character['canDeleteBankTransactions'] = isPrivilegedUserRole($currentUserRole);
    $character['canManageSecurities'] = canManageCharacterSecurities($character, $currentUserRole, $currentUserId);
    $character['canApproveSecuritiesSnapshots'] = canApproveCharacterSecuritiesSnapshots($character, $currentUserRole, $currentUserId);
    $character['defaultBankTransferDate'] = getDefaultBankTransferDate();
    $character['bankTransferTargets'] = $character['canTransferMoney']
        ? getBankTransferTargets($pdo, $characterId)
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
    $character['virtualCompanyShareLivingStandardIncome'] = getCharacterVirtualCompanyShareLivingStandardIncome($pdo, $characterId);
    $character['draftBankAccountAmount'] = getDraftBankAccountAmountForCharacter($pdo, $character);
    $securitiesPortfolio = getCharacterSecuritiesPortfolio($pdo, $character);
    $character['securitiesaccount'] = $securitiesPortfolio['balance'];
    $character['securitiesManagerType'] = $securitiesPortfolio['managerType'];
    $character['securitiesManagerCharacterId'] = $securitiesPortfolio['managerCharacterId'];
    $character['securitiesRiskProfile'] = $securitiesPortfolio['riskProfile'];
    $character['securitiesManagerSkillLevel'] = $securitiesPortfolio['managerSkillLevel'];
    $character['securitiesManagerDisplayName'] = $securitiesPortfolio['managerDisplayName'];
    $character['securitiesRiskProfileOptions'] = getCharacterSecuritiesRiskProfileOptions();
    $character['securitiesManagerOptions'] = $character['canManageSecurities']
        ? getCharacterSecuritiesManagerOptions($pdo, $characterId)
        : [];
    $character['bankTransactions'] = $canViewEconomy
        ? getCharacterBankTransactions($pdo, $characterId)
        : [];
    $character['canCreateEconomySnapshots'] = canManageCharacterEconomySnapshots($character, $currentUserRole, $currentUserId);
    $character['economySnapshotEventOptions'] = $character['canCreateEconomySnapshots']
        ? getCharacterEconomySnapshotEventOptions($pdo, $characterId)
        : [];
    $character['economySnapshots'] = $canViewEconomy
        ? getCharacterEconomySnapshots($pdo, $characterId, $character['canApproveSecuritiesSnapshots'])
        : [];
    $character['companyShares'] = $canViewEconomy
        ? getCharacterCompanyShares($pdo, $character, $currentUserRole, $currentUserId)
        : [];
    $character['companySharePurchaseOptions'] = $canViewEconomy
        ? getCharacterCompanySharePurchaseOptions($pdo, $character, $currentUserRole, $currentUserId)
        : [];

    return $character;
}

/** @return array{entries: list<array<string, mixed>>, availableEvents: list<array<string, mixed>>} */
function aetherBuildCharacterDiaryReadModel(PDO $pdo, int $characterId): array
{
    $entries = array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        $row['idCharacter'] = (int) $row['idCharacter'];
        $row['idEvent'] = (int) $row['idEvent'];
        return aetherSanitizeCharacterDiaryRow($row);
    }, aetherFetchCharacterDiaryEntries($pdo, $characterId));

    $availableEvents = array_map(static function (array $row): array {
        $row['id'] = (int) $row['id'];
        return $row;
    }, aetherFetchCharacterDiaryAvailableEvents($pdo, $characterId));

    return ['entries' => $entries, 'availableEvents' => $availableEvents];
}

/** @return array{personal_background: string, knowledge: string, nature: string, demeanour: string} */
function aetherBuildCharacterSectionsReadModel(PDO $pdo, int $characterId): array
{
    $sections = [
        'personal_background' => '',
        'knowledge' => '',
        'nature' => '',
        'demeanour' => '',
    ];

    foreach (aetherFetchCharacterSectionRows($pdo, $characterId) as $row) {
        $section = (string) ($row['section'] ?? '');
        if (in_array($section, AETHER_CHARACTER_RICH_TEXT_SECTIONS, true)) {
            $sections[$section] = aetherSanitizeCharacterRichText((string) ($row['content'] ?? ''));
        }
    }

    return $sections;
}
