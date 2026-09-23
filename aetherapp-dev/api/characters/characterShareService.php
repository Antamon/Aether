<?php
declare(strict_types=1);

require_once __DIR__ . '/characterFinanceService.php';
require_once __DIR__ . '/characterShareRepository.php';
require_once __DIR__ . '/companyShareUtils.php';
require_once __DIR__ . '/../companies/companyUtils.php';

function aetherSharePrice(string $companyValue): string
{
    return aetherDecimalMultiplyRatio(aetherFinanceDecimal($companyValue), 1, 100);
}

/** Recheck current character access before replaying a stored share-purchase response. */
function aetherAuthorizeCompanySharePurchaseReplay(PDO $pdo, array $user, array $input): void
{
    $companyId = (int) $input['idCompany'];
    if (!isset(aetherShareLockCompanies($pdo, [$companyId])[$companyId])) {
        throw new AetherFinanceException(404, 'Bedrijf niet gevonden.');
    }
    $character = aetherFinanceLockCharacter($pdo, (int) $input['idCharacter']);
    if ($character === null) throw new AetherFinanceException(404, 'Personage niet gevonden.');
    if (!canIncreaseCompanyShareRank($character, (string) $user['role'], (int) $user['id'])) {
        throw new AetherFinanceException(403, 'Je hebt geen rechten om een nieuw aandeel te kopen voor dit personage.');
    }
}

/** Recheck current character access before replaying a stored share response. */
function aetherAuthorizeCompanyShareReplay(PDO $pdo, array $user, array $input): void
{
    $link = aetherShareFetchLink($pdo, (int) $input['idLinkCharacterTrait']);
    if ($link === null) {
        // A decrease can legitimately have removed the last link. The completed row still binds
        // the replay to the same authenticated user and exact validated payload.
        if ((string) $input['action'] === 'decrease_rank') return;
        throw new AetherFinanceException(404, 'Aandelen-trait niet gevonden.');
    }
    $character = aetherFinanceLockCharacter($pdo, (int) $link['idCharacter']);
    if ($character === null) throw new AetherFinanceException(404, 'Personage niet gevonden.');
    $action = (string) $input['action'];
    $allowed = in_array($action, ['assign_company', 'clear_company'], true)
        ? canManageCompanyShareAssignments($character, (string) $user['role'], (int) $user['id'])
        : ($action === 'increase_rank'
            ? canIncreaseCompanyShareRank($character, (string) $user['role'], (int) $user['id'])
            : canDecreaseCompanyShareRank($character, (string) $user['role'], (int) $user['id']));
    if (!$allowed) throw new AetherFinanceException(403, 'Je hebt geen rechten om dit aandeel te wijzigen.');
}

/** @return array<string, mixed> */
function aetherBuyCompanyShare(PDO $pdo, array $user, array $input): array
{
    $characterId = (int) $input['idCharacter'];
    $companyId = (int) $input['idCompany'];
    $companies = aetherShareLockCompanies($pdo, [$companyId]);
    if (!isset($companies[$companyId])) throw new AetherFinanceException(404, 'Bedrijf niet gevonden.');
    $character = aetherFinanceLockCharacter($pdo, $characterId);
    if ($character === null) throw new AetherFinanceException(404, 'Personage niet gevonden.');
    if (!canIncreaseCompanyShareRank($character, (string) $user['role'], (int) $user['id'])) {
        throw new AetherFinanceException(403, 'Je hebt geen rechten om een nieuw aandeel te kopen voor dit personage.');
    }
    if ((string) $character['state'] === 'draft') {
        throw new AetherFinanceException(400, 'Nieuwe aandelen koop je pas zodra het personage niet meer in draft staat.');
    }
    $company = enrichCompanyWithType($companies[$companyId]);
    $price = aetherSharePrice((string) $company['companyValue']);
    if (aetherDecimalCompare($price, '0.00') <= 0) {
        throw new AetherFinanceException(400, 'De bedrijfswaarde is ongeldig voor een aandelenaankoop.');
    }
    if (aetherDecimalCompare($price, (string) $character['bankaccount']) > 0) {
        throw new AetherFinanceException(400, 'Onvoldoende saldo op de bankrekening.');
    }
    if (aetherShareAllocatedPercentage($pdo, $companyId) >= 100) {
        throw new AetherFinanceException(400, 'Dit bedrijf heeft geen vrij aandeel meer voor +1%.');
    }
    $traitId = findCompanyShareTraitIdForCompanyType(
        $pdo, (string) $character['class'], (string) $input['shareClass'], (string) $company['companyTypeKey']
    );
    if ($traitId === null || $traitId <= 0) {
        throw new AetherFinanceException(400, 'Geen passend aandelen-trait gevonden voor dit personage en bedrijf.');
    }
    if (aetherShareFindCharacterTraitLink($pdo, $characterId, $traitId) !== null) {
        throw new AetherFinanceException(400, 'Dit aandelen-trait is al gekoppeld aan het personage. Gebruik de bestaande aandeelkaart om verder te verhogen.');
    }
    $linkId = aetherShareInsertTraitLink($pdo, $characterId, $traitId);
    if ($linkId <= 0) throw new RuntimeException('Share link insert failed.');
    aetherShareUpsertCompanyLink($pdo, $linkId, $companyId, 0);
    aetherFinanceAdjustBankBalance($pdo, $characterId, '-' . $price);
    return ['success' => true, 'idLinkCharacterTrait' => $linkId, 'unitPrice' => (float) $price];
}

/** @return array<string, mixed> */
function aetherSaveCompanyShare(PDO $pdo, array $user, array $input): array
{
    $action = (string) $input['action'];
    $linkId = (int) $input['idLinkCharacterTrait'];
    $initial = aetherShareFetchLink($pdo, $linkId);
    if ($initial === null) throw new AetherFinanceException(404, 'Aandelen-trait niet gevonden.');
    $characterId = (int) $initial['idCharacter'];
    $requestedCompanyId = isset($input['idCompany']) ? (int) $input['idCompany'] : 0;
    $currentCompanyId = (int) ($initial['idCompany'] ?? 0);
    aetherShareLockCompanies($pdo, [$currentCompanyId, $requestedCompanyId]);
    $character = aetherFinanceLockCharacter($pdo, $characterId);
    if ($character === null) throw new AetherFinanceException(404, 'Personage niet gevonden.');
    $link = aetherShareFetchLink($pdo, $linkId, true);
    if ($link === null) throw new AetherFinanceException(404, 'Aandelen-trait niet gevonden.');
    if ((int) ($link['idCompany'] ?? 0) !== $currentCompanyId) {
        throw new AetherFinanceException(
            409,
            'De aandelenkoppeling is intussen gewijzigd. Laad het personage opnieuw en probeer opnieuw.'
        );
    }
    $trait = getTraitDefinition($pdo, (int) $link['idTrait']);
    if ($trait !== null) {
        $trait['rank'] = (int) $link['rankValue'];
        $trait['baseRank'] = (int) $link['rankValue'];
        $trait['shareExtraRank'] = (int) $link['extraPercentage'];
    }
    if (!$trait || !isCompanyShareTrait($trait)) {
        throw new AetherFinanceException(400, 'Deze trait is geen aandelen-trait.');
    }

    if (in_array($action, ['assign_company', 'clear_company'], true)) {
        if (!canManageCompanyShareAssignments($character, (string) $user['role'], (int) $user['id'])) {
            throw new AetherFinanceException(403, 'Je hebt geen rechten om aandelenbedrijven te koppelen.');
        }
        if ($action === 'clear_company') {
            aetherShareUpsertCompanyLink($pdo, $linkId, null, (int) $link['extraPercentage']);
            return ['success' => true];
        }
        $companies = aetherShareLockCompanies($pdo, [$requestedCompanyId]);
        if (!isset($companies[$requestedCompanyId])) throw new AetherFinanceException(404, 'Bedrijf niet gevonden.');
        $company = enrichCompanyWithType($companies[$requestedCompanyId]);
        if (!companyMatchesShareTrait($trait, $company)) {
            throw new AetherFinanceException(400, 'Dit bedrijf past niet bij het gekozen aandeeltype.');
        }
        $percentage = getCompanyShareTotalRank($trait);
        if (aetherShareAllocatedPercentage($pdo, $requestedCompanyId, $linkId) + $percentage > 100) {
            throw new AetherFinanceException(400, 'Dit bedrijf heeft niet genoeg vrije aandelen voor deze koppeling.');
        }
        aetherShareUpsertCompanyLink($pdo, $linkId, $requestedCompanyId, (int) $link['extraPercentage']);
        return ['success' => true];
    }

    $companyId = (int) ($link['idCompany'] ?? 0);
    if ($companyId <= 0) throw new AetherFinanceException(400, 'Koppel eerst een bedrijf aan dit aandeel.');
    $companies = aetherShareLockCompanies($pdo, [$companyId]);
    if (!isset($companies[$companyId])) throw new AetherFinanceException(404, 'Het gekoppelde bedrijf bestaat niet meer.');
    $company = enrichCompanyWithType($companies[$companyId]);

    if ($action === 'increase_rank') {
        if (!canIncreaseCompanyShareRank($character, (string) $user['role'], (int) $user['id'])) {
            throw new AetherFinanceException(403, 'Je hebt geen rechten om dit aandeel te verhogen.');
        }
        if (!companyMatchesShareTrait($trait, $company)) {
            throw new AetherFinanceException(400, 'Het gekoppelde bedrijf past niet langer bij dit aandeeltype.');
        }
        if (aetherShareAllocatedPercentage($pdo, $companyId, $linkId) + getCompanyShareTotalRank($trait) + 1 > 100) {
            throw new AetherFinanceException(400, 'Dit bedrijf heeft geen vrij aandeel meer voor +1%.');
        }
        $price = aetherSharePrice((string) $company['companyValue']);
        if (aetherDecimalCompare($price, '0.00') <= 0) throw new AetherFinanceException(400, 'De bedrijfswaarde is ongeldig voor een aandelenverhoging.');
        if (aetherDecimalCompare($price, (string) $character['bankaccount']) > 0) throw new AetherFinanceException(400, 'Onvoldoende saldo op de bankrekening.');
        aetherShareUpsertCompanyLink($pdo, $linkId, $companyId, (int) $link['extraPercentage'] + 1);
        aetherFinanceAdjustBankBalance($pdo, $characterId, '-' . $price);
        return ['success' => true, 'nextPercentageCost' => (float) $price];
    }

    if (!canDecreaseCompanyShareRank($character, (string) $user['role'], (int) $user['id'])) {
        throw new AetherFinanceException(403, 'Je hebt geen rechten om dit aandeel te verlagen.');
    }
    $percentage = getCompanyShareTotalRank($trait);
    if ($percentage <= 0) throw new AetherFinanceException(400, 'Dit aandeel heeft geen percentage meer om te verkopen.');
    $sale = aetherSharePrice((string) $company['companyValue']);
    if (aetherDecimalCompare($sale, '0.00') <= 0) throw new AetherFinanceException(400, 'De bedrijfswaarde is ongeldig voor een aandelenverkoop.');
    $base = getCompanyShareBaseRank($trait);
    $extra = getCompanyShareExtraRank($trait);
    if ($extra > 0) $extra--; else $base = max(0, $base - 1);
    if ($base <= 0 && $extra <= 0) aetherShareDeleteLink($pdo, $linkId);
    else {
        if ($base !== getCompanyShareBaseRank($trait)) aetherShareSetBaseRank($pdo, $linkId, $base);
        aetherShareUpsertCompanyLink($pdo, $linkId, $companyId, $extra);
    }
    aetherFinanceAdjustBankBalance($pdo, $characterId, $sale);
    return ['success' => true, 'saleValue' => (float) $sale];
}
