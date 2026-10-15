<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../shared/response.php';

try {
    /** Each thin endpoint chooses exactly one operation before including this file. */
    if (!isset($aetherShareAction)) throw new LogicException('Aandelenactie ontbreekt.');
    require_once __DIR__ . '/../../db.php';
    require_once __DIR__ . '/shareService.php';
    if (in_array($aetherShareAction, ['market_search', 'share_discovery', 'assessment_update'], true)) {
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
        'draft_purchase' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idSnapshot' => ['type' => 'int', 'min' => 0],
            'shareCount' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 1000000],
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
        ],
        'add_purchase' => [
            'idCompany' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idEvent' => ['type' => 'int', 'required' => true, 'min' => 1],
            'shareCount' => ['type' => 'int', 'required' => true, 'min' => 1, 'max' => 1000000],
            'unitPrice' => ['type' => 'decimal', 'required' => true, 'minExclusive' => '0.00', 'max' => '9999999999.99'],
        ],
        'market_search' => [
            'idCharacter' => ['type' => 'int', 'required' => true, 'min' => 1],
            'idEvent' => ['type' => 'int', 'min' => 1],
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
    $input = aetherValidateInput(aetherReadJsonObject(), $schema);
    $key = aetherRequireIdempotencyKey();
    $response = match ($aetherShareAction) {
        'draft_purchase' => aetherShareBuyDraft($pdo, $currentUser, $input, $key),
        'private_offer' => aetherShareCreatePrivateOffer($pdo, $currentUser, $input, $key),
        'holder_offer' => aetherShareCreateHolderOffer($pdo, $currentUser, $input, $key),
        'cancel_offer' => aetherShareCancelOffer($pdo, $currentUser, $input, $key),
        'buy_offer' => aetherShareBuyOffer($pdo, $currentUser, $input, $key),
        'add_purchase' => aetherShareAddPurchase($pdo, $currentUser, $input, $key),
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
