<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../shared/response.php';

try {
    /** Each thin endpoint chooses exactly one operation before including this file. */
    if (!isset($aetherShareAction)) throw new LogicException('Aandelenactie ontbreekt.');
    require_once __DIR__ . '/../../db.php';
    require_once __DIR__ . '/shareService.php';
    if (in_array($aetherShareAction, ['market_search', 'share_discovery', 'assessment_update', 'research_update', 'discovery_update'], true)) {
        require_once __DIR__ . '/shareSearchService.php';
    }
    require_once __DIR__ . '/../shared/request.php';
    require_once __DIR__ . '/../shared/validation.php';

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        aetherJsonResponse(['errorCode' => 'shares.method_not_allowed', 'error' => 'Gebruik POST.'], 405);
    }
    $currentUser = aetherRequireAuthenticatedUser($pdo);
    aetherRequireCsrfToken();
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $schemas = [
        'company_holding_add' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idHoldingCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'shareCount' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 1000000],
        ],
        'discovery_update' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idEvent' => ['type' => 'int', 'required' => true, 'min' => 1],
            'shareCount' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 1000000],
            'unitPrice' => ['type' => 'decimal', 'required' => true, 'minExclusive' => '0.00', 'max' => '9999999999.99'],
        ],
        'research_update' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idEvent' => ['type' => 'int', 'required' => true, 'min' => 1],
            'revision' => ['type' => 'string', 'required' => true, 'minLength' => 64, 'maxLength' => 64],
        ],
        'draft_purchase' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idSnapshot' => ['type' => 'int', 'min' => 0],
            'shareCount' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 1000000],
            'purchaseYear' => ['type' => 'int', 'min' => 1000, 'max' => 9899],
            'purchaseQuarter' => ['type' => 'int', 'min' => 1, 'max' => 4],
        ],
        'foundation' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'initialCompanyValue' => ['type' => 'decimal', 'required' => true, 'min' => '0.00', 'max' => '9999999999.99'],
            'initialShareCount' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 1000000],
            'shareMarketPrice' => ['type' => 'decimal', 'min' => '0.00', 'max' => '9999999999.99'],
            'anonymousShareCount' => ['type' => 'int', 'min' => 0, 'max' => 1000000],
            'stability' => ['type' => 'int', 'min' => -7, 'max' => 7],
            'profitability' => ['type' => 'int', 'min' => -7, 'max' => 7],
        ],
        'private_offer' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idSnapshot' => ['type' => 'int', 'min' => 1],
            'idOfferingCharacter' => ['type' => 'int', 'required' => true, 'min' => 0],
            'idRecipientCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'shareCount' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 1000000],
            'unitPrice' => ['type' => 'decimal', 'required' => true, 'minExclusive' => '0.00', 'max' => '9999999999.99'],
        ],
        'assessment_update' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idEvent' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idAssessment' => ['type' => 'int', 'required' => true, 'min' => 1],
            'discoveryRoll' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 100],
            'quantityRoll' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 100],
            'priceRoll' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 200],
        ],
        'holder_offer' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idSellingCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idRecipientCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'shareCount' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 1000000],
            'unitPrice' => ['type' => 'decimal', 'required' => true, 'minExclusive' => '0.00', 'max' => '9999999999.99'],
        ],
        'cancel_offer' => [
            'idOffer' => ['type' => 'int', 'required' => true, 'min' => 1],
        ],
        'buy_offer' => [
            'idOffer' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'shareCount' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 1000000],
            'idEvent' => ['type' => 'int', 'required' => true, 'min' => 1],
            'purchaseYear' => ['type' => 'int', 'min' => 1000, 'max' => 9899],
            'purchaseQuarter' => ['type' => 'int', 'min' => 1, 'max' => 4],
        ],
        'add_purchase' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idEvent' => ['type' => 'int', 'min' => 1],
            'purchaseYear' => ['type' => 'int', 'min' => 1000, 'max' => 9899],
            'purchaseQuarter' => ['type' => 'int', 'min' => 1, 'max' => 4],
            'shareCount' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 1000000],
            'unitPrice' => ['type' => 'decimal', 'minExclusive' => '0.00', 'max' => '9999999999.99'],
        ],
        'update_purchase' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idTransaction' => ['type' => 'int', 'required' => true, 'min' => 1],
            'revision' => ['type' => 'int', 'required' => true, 'min' => 0],
            'shareCount' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 1000000],
            'purchaseYear' => ['type' => 'int', 'required' => true, 'min' => 1000, 'max' => 9899],
            'purchaseQuarter' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 4],
            'adjustBank' => ['type' => 'bool', 'required' => true],
        ],
        'initialize_purchase_history' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
        ],
        'remove_holding' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
        ],
        'market_search' => [
            'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idEvent' => ['type' => 'int', 'min' => 1],
            'idCompany' => ['type' => 'int', 'min' => 1],
        ],
        'share_discovery' => [
            'idOffer' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idRecipientCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idEvent' => ['type' => 'int', 'min' => 1],
        ],
        'initial' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'initialCompanyValue' => ['type' => 'decimal', 'required' => true, 'min' => '0.00', 'max' => '9999999999999999.99'],
            'initialShareCount' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 1000000],
        ],
        'value' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idSnapshot' => ['type' => 'int', 'required' => true, 'min' => 1],
            'companyValue' => ['type' => 'decimal', 'required' => true, 'min' => '0.00', 'max' => '9999999999.99'],
        ],
        'anonymous' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idSnapshot' => ['type' => 'int', 'required' => true, 'min' => 1],
            'shareCount' => ['type' => 'int', 'required' => true, 'min' => 0, 'max' => 1000000],
        ],
        'buyback' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'shareCount' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 1000000],
        ],
        'listing' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'isShareListed' => ['type' => 'bool', 'required' => true],
        ],
        'market_price' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idSnapshot' => ['type' => 'int', 'required' => true, 'min' => 1],
            'unitPrice' => ['type' => 'decimal', 'required' => true, 'minExclusive' => '0.00', 'max' => '9999999999.99'],
        ],
        'board' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'isBoardMember' => ['type' => 'bool', 'required' => true],
        ],
    ];
    $schema = $schemas[$aetherShareAction] ?? null;
    if ($schema === null) throw new LogicException('Onbekende aandelenactie.');
    $rawInput = aetherReadJsonObject();
    $knownEventIds = $rawInput['knownEventIds'] ?? null;
    if ($aetherShareAction === 'research_update') unset($rawInput['knownEventIds']);
    $input = aetherValidateInput($rawInput, $schema);
    if ($aetherShareAction === 'research_update') {
        if (!is_array($knownEventIds) || !array_is_list($knownEventIds)) throw new AetherValidationException(['Kies de gekende gebeurtenissen.']);
        $input['knownEventIds'] = $knownEventIds;
        if (count($input['knownEventIds']) > 10000) throw new AetherValidationException(['Te veel gebeurtenissen.']);
        foreach ($input['knownEventIds'] as $id) if (!is_int($id) || $id < 1) throw new AetherValidationException(['Ongeldige gebeurtenis.']);
    }
    $key = aetherRequireIdempotencyKey();
    $response = match ($aetherShareAction) {
        'company_holding_add' => aetherShareAddCompanyHolding($pdo, $currentUser, $input, $key),
        'discovery_update' => aetherShareSaveDiscovery($pdo, $currentUser, $input, $key),
        'research_update' => aetherCompanyResearchSave($pdo, $currentUser, $input, $key),
        'draft_purchase' => aetherShareBuyDraft($pdo, $currentUser, $input, $key),
        'private_offer' => aetherShareCreatePrivateOffer($pdo, $currentUser, $input, $key),
        'holder_offer' => aetherShareCreateHolderOffer($pdo, $currentUser, $input, $key),
        'cancel_offer' => aetherShareCancelOffer($pdo, $currentUser, $input, $key),
        'buy_offer' => aetherShareBuyOffer($pdo, $currentUser, $input, $key),
        'add_purchase' => aetherShareAddPurchase($pdo, $currentUser, $input, $key),
        'update_purchase' => aetherShareUpdatePurchase($pdo, $currentUser, $input, $key),
        'initialize_purchase_history' => aetherShareInitializePurchaseHistory($pdo, $currentUser, $input, $key),
        'remove_holding' => aetherShareRemoveCharacterHolding($pdo, $currentUser, $input, $key),
        'market_search' => aetherShareSearch($pdo, $currentUser, $input, $key),
        'share_discovery' => aetherShareShareDiscovery($pdo, $currentUser, $input, $key),
        'assessment_update' => aetherShareUpdateAssessment($pdo, $currentUser, $input, $key),
        default => aetherShareManageCompany($pdo, $currentUser, $aetherShareAction, $input, $key),
    };
    aetherJsonResponse($response);
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (AetherShareException $e) {
    aetherJsonResponse(['errorCode' => $e->getErrorCode(), 'error' => $e->getMessage()], $e->getHttpStatus());
} catch (AetherIdempotencyException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (DomainException|InvalidArgumentException $e) {
    aetherJsonError(422, $e->getMessage());
} catch (Throwable $e) {
    $logMessage = 'share write failed (' . ($aetherShareAction ?? 'unknown') . '): '
        . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    error_log($logMessage);
    if (basename(dirname(__DIR__, 2)) === 'aetherapp-dev') {
        @error_log('[' . date('c') . '] ' . $logMessage . PHP_EOL, 3, __DIR__ . '/share-errors.log');
    }
    aetherJsonError(500, 'De aandelenactie kon niet worden uitgevoerd.');
}
