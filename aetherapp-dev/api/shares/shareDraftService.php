<?php
declare(strict_types=1);
require_once __DIR__ . '/shareDraftBudget.php';

function aetherShareBuyDraft(PDO $pdo, array $user, array $input, string $key): array
{
    return aetherShareMutation($pdo, $user, 'draft.purchase', $key, $input,
        static function (array $companies) use ($pdo, $user, $input, $key): array {
            $companyId = (int) $input['idCompany']; $buyerId = (int) $input['idCharacter'];
            $company = aetherShareRequireCompany($companies, $companyId);
            $affected = array_values(array_unique([...aetherShareHolderIds($pdo, $companyId), $buyerId]));
            $characters = aetherFinanceLockCharacters($pdo, $affected);
            $buyer = aetherShareRequireCharacter($characters, $buyerId, $user, false);
            if ($buyer['state'] !== 'draft') throw new AetherShareException(409, 'Startkapitaal is alleen beschikbaar tijdens Draft.');
            $budget = aetherShareDraftBudget($pdo, $buyerId);
            if (!$budget || $budget['closedAt'] !== null) throw new AetherShareException(409, 'Kies eerst de statuseigenschap Aandelen tijdens Draft.');
            $quotes = aetherShareDraftQuotes($pdo, $companyId);
            $selected = $input['idSnapshot'] ?? null;
            $quote = null;
            foreach ($quotes as $candidate) {
                if ($selected === null ? $candidate['isCurrent'] : (int) $candidate['idSnapshot'] === (int) $selected) { $quote = $candidate; break; }
            }
            if (!$quote || $quote['unitPrice'] === null || aetherDecimalCompare((string) $quote['unitPrice'], '0.00') <= 0) {
                throw new AetherShareException(409, 'Deze snapshot heeft geen geldige marktprijs.');
            }
            if (!$quote['isCurrent'] && !aetherCanManageShareCompany($user)) throw new AetherShareException(403, 'Alleen admins en directors kunnen een historische snapshot kiezen.');
            $quantity = (int) $input['shareCount'];
            $stock = aetherShareStock($pdo, $companyId, (int) $company['issuedShareCount']);
            if ($quantity > $stock['free']) throw new AetherShareException(409, 'Onvoldoende vrije aandelen.');
            $total = aetherSharePurchaseTotal((string) $quote['unitPrice'], $quantity);
            if (aetherDecimalCompare($budget['remainingAmount'], $total) < 0) throw new AetherShareException(409, 'Onvoldoende beschikbaar aandelenkapitaal.');
            $before = aetherNormalizeDecimal((string) $company['companyValue']); $after = aetherDecimalAdd($before, $total);
            if (aetherDecimalCompare($after, '9999999999.99') > 0) throw new AetherShareException(409, 'De bedrijfswaarde overschrijdt het toegestane bereik.');
            $stmt = $pdo->prepare('UPDATE tblCharacterShareDraftBudget SET spentAmount = spentAmount + :amount WHERE idCharacter = :idCharacter AND closedAt IS NULL');
            $stmt->execute(['amount' => $total, 'idCharacter' => $buyerId]);
            aetherShareAddHolding($pdo, $companyId, $buyerId, $quantity);
            $stmt = $pdo->prepare('UPDATE tblCompany SET companyValue = :value WHERE id = :id');
            $stmt->execute(['value' => $after, 'id' => $companyId]);
            $details = json_encode(['draftCapitalPurchase' => true, 'idSnapshot' => (int) $quote['idSnapshot']], JSON_THROW_ON_ERROR);
            $transactionId = aetherShareInsertTransaction($pdo, [
                'idCompany' => $companyId, 'idCharacter' => $buyerId, 'idCounterpartyCharacter' => null,
                'idOffer' => null, 'idEvent' => $quote['idEvent'] === null ? null : (int) $quote['idEvent'], 'actorUserId' => (int) $user['id'],
                'operationCode' => 'buy_free', 'shareCount' => $quantity, 'unitPrice' => $quote['unitPrice'], 'totalAmount' => $total,
                'companyValueBefore' => $before, 'companyValueAfter' => $after, 'requestKey' => $key, 'detailsJson' => $details,
            ]);
            aetherShareInsertValueHistory($pdo, $companyId, $transactionId, (int) $user['id'], 'free_share_purchase', $before, $after, $details);
            aetherShareRevalue($pdo, $affected);
            return ['transactionId' => $transactionId, 'totalAmount' => $total, 'remainingAmount' => aetherDecimalSubtract($budget['remainingAmount'], $total)];
        },
        static function (array $companies) use ($pdo, $user, $input): void {
            aetherShareRequireCompany($companies, (int) $input['idCompany']);
            $character = aetherFetchCharacterAccessRecord($pdo, (int) $input['idCharacter']);
            if (!$character || !aetherCanTradeForCharacter($user, $character)) throw new AetherShareException(403, 'Geen rechten voor dit personage.');
        });
}
