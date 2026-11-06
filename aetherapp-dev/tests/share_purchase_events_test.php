<?php
declare(strict_types=1);

// Exercise the real purchase services and idempotency layer with isolated storage.
$appRoot = $argv[1] ?? dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/aether-purchase-events-' . bin2hex(random_bytes(5));
foreach (['shares', 'shared', 'characters', 'companies'] as $folder) mkdir($fixture . '/api/' . $folder, 0777, true);
foreach (['shares/shareService.php', 'shares/shareMarketEvent.php', 'shares/sharePurchaseHistory.php', 'shares/shareException.php', 'shares/shareDraftService.php', 'shares/shareDraftBudget.php',
          'shares/shareRules.php', 'shares/shareOwnershipRepository.php', 'shares/shareCompanyOwnershipService.php', 'shared/decimal.php', 'shared/idempotency.php', 'shared/response.php'] as $file) {
    copy($appRoot . '/api/' . $file, $fixture . '/api/' . $file);
}
file_put_contents($fixture . '/api/shares/shareAccess.php', <<<'PHP'
<?php
require_once __DIR__ . '/shareException.php';
function aetherRequireShareMigration(PDO $pdo): void {}
function aetherCanManageShareCompany(array $u): bool { return in_array($u['role'], ['director', 'administrator'], true); }
function aetherCanTradeForCharacter(array $u, array $c): bool { return aetherCanManageShareCompany($u) || ($c['type'] === 'player' && $c['idUser'] === $u['id']); }
function aetherFetchCharacterAccessRecord(PDO $p, int $id): ?array { return $p->state['characters'][$id] ?? null; }
PHP);
file_put_contents($fixture . '/api/shares/shareRepository.php', <<<'PHP'
<?php
function aetherSharesLockCompany(PDO $p, int $id): array { return isset($p->state['companies'][$id]) ? [$id => $p->state['companies'][$id]] : []; }
function aetherShareHolderIds(PDO $p, int $id): array { return array_keys($p->state['holdings'][$id] ?? []); }
function aetherShareStock(PDO $p, int $id, int $issued): array { $held = array_sum($p->state['holdings'][$id] ?? []); $anonymous = $p->state['anonymous'][$id] ?? 0; return ['issued' => $issued, 'held' => $held, 'anonymous' => $anonymous, 'free' => $issued - $held - $anonymous]; }
function aetherLatestCompanySnapshotId(PDO $p, int $id): ?int { return ($p->state['snapshots'][$id] ?? []) ? (int) array_key_last($p->state['snapshots'][$id]) : null; }
function aetherShareLockOffer(PDO $p, int $id): ?array { return $p->state['offers'][$id] ?? null; }
function aetherShareOfferRecipient(PDO $p, int $id, int $c): bool { return in_array($c, $p->state['recipients'][$id] ?? [], true); }
function aetherShareAddHolding(PDO $p, int $co, int $c, int $n): void { $p->state['holdings'][$co][$c] = ($p->state['holdings'][$co][$c] ?? 0) + $n; }
function aetherShareLockHolding(PDO $p, int $co, int $c): ?array { return isset($p->state['holdings'][$co][$c]) ? ['shareCount' => $p->state['holdings'][$co][$c]] : null; }
function aetherShareRemoveHolding(PDO $p, int $co, int $c, int $old, int $n): void { $p->state['holdings'][$co][$c] -= $n; }
function aetherShareInsertTransaction(PDO $p, array $row): int { $row['id'] = count($p->state['transactions']) + 1; $p->state['transactions'][] = $row; return $row['id']; }
function aetherShareInsertValueHistory(PDO $p, int $co, ?int $tx, int $u, string $reason, string $before, string $after, ?string $details = null): void { $p->state['history'][] = [$tx, $before, $after]; }
function aetherSharePortfolioValue(PDO $p, int $c): string { return '0.00'; }
function aetherShareValueAfterPurchase(PDO $p, array $company, string $delta): string { return $p->state['recordedValues'][$company['id']] ?? aetherDecimalAdd($company['companyValue'], $delta); }
function aetherShareSaveStatus(PDO $p, int $c, string $v, array $status): void { $p->state['status'][$c] = $status; }
PHP);
file_put_contents($fixture . '/api/characters/characterFinanceRepository.php', <<<'PHP'
<?php
function aetherFinanceLockCharacters(PDO $p, array $ids): array { return array_intersect_key($p->state['characters'], array_flip($ids)); }
function aetherFinanceAdjustBankBalance(PDO $p, int $id, string $delta): void { $p->state['characters'][$id]['bankaccount'] = aetherDecimalAdd($p->state['characters'][$id]['bankaccount'], $delta); }
PHP);
file_put_contents($fixture . '/api/companies/companyLeadershipRules.php', '<?php');
file_put_contents($fixture . '/api/companies/companyUtils.php', '<?php function aetherCompanySnapshotHistoryAvailable(PDO $pdo): bool { return true; }');
require $fixture . '/api/shares/shareService.php';

final class PurchaseEventPdo extends PDO
{
    public array $state;
    private ?array $backup = null;
    public function __construct(array $state) { $this->state = $state; }
    public function inTransaction(): bool { return $this->backup !== null; }
    public function beginTransaction(): bool { $this->backup = $this->state; return true; }
    public function commit(): bool { $this->backup = null; return true; }
    public function rollBack(): bool { $this->state = $this->backup; $this->backup = null; return true; }
    public function exec(string $statement): int|false { return 0; }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    { $stmt = new PurchaseEventStatement($this, $query); $stmt->execute(); return $stmt; }
    public function prepare(string $query, array $options = []): PDOStatement|false
    { return new PurchaseEventStatement($this, $query); }
}
final class PurchaseEventStatement extends PDOStatement
{
    private array $rows = [];
    public function __construct(private PurchaseEventPdo $pdo, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $v = $params ?? []; $s = &$this->pdo->state; $q = $this->sql;
        $this->rows = [];
        if (str_contains($q, 'FROM tblSchemaMigration')) {
            $this->rows = [];
        } elseif (str_contains($q, 'INSERT INTO tblApiIdempotency')) {
            $key = $v['idUser'] . ':' . $v['operation'] . ':' . $v['requestKey'];
            $s['keys'][$key] ??= ['payloadHash' => $v['payloadHash'], 'status' => 'processing'];
        } elseif (str_contains($q, 'FROM tblApiIdempotency')) {
            $key = $v['idUser'] . ':' . $v['operation'] . ':' . $v['requestKey'];
            $this->rows = [$s['keys'][$key]];
        } elseif (str_contains($q, 'UPDATE tblApiIdempotency')) {
            $key = $v['idUser'] . ':' . $v['operation'] . ':' . $v['requestKey'];
            $s['keys'][$key]['status'] = 'completed'; $s['keys'][$key]['responseJson'] = $v['responseJson'];
        } elseif (str_contains($q, 'FROM tblEvent')) {
            $this->rows = isset($v['idEvent']) ? (isset($s['events'][$v['idEvent']]) ? [$s['events'][$v['idEvent']]] : []) : array_values($s['events']);
        } elseif (str_contains($q, 'SELECT createdAt FROM tblCharacter')) {
            $this->rows = isset($s['characters'][$v['idCharacter']]) ? [['createdAt' => $s['characters'][$v['idCharacter']]['createdAt'] ?? null]] : [];
        } elseif (str_contains($q, 'SELECT') && str_contains($q, 'FROM tblCharacterShareDraftBudget')) {
            $this->rows = isset($s['budgets'][$v['idCharacter']]) ? [$s['budgets'][$v['idCharacter']]] : [];
        } elseif (str_contains($q, 'INSERT INTO tblCharacterShareDraftBudget')) {
            $old = $s['budgets'][$v['idCharacter']] ?? ['spentAmount' => '0.00', 'closedAt' => null];
            $s['budgets'][$v['idCharacter']] = array_merge($old, ['purchasedLevel' => $v['level'], 'budgetAmount' => $v['amount']]);
        } elseif (str_contains($q, 'DELETE FROM tblCharacterShareDraftBudget')) {
            unset($s['budgets'][$v['idCharacter']]);
        } elseif (str_contains($q, 'UPDATE tblCharacterShareDraftBudget SET spentAmount')) {
            $s['budgets'][$v['idCharacter']]['spentAmount'] = $v['spent'] ?? aetherDecimalAdd($s['budgets'][$v['idCharacter']]['spentAmount'], $v['amount']);
        } elseif (str_contains($q, 'UPDATE tblCharacterShareDraftBudget SET closedAt')) {
            if (isset($s['budgets'][$v['idCharacter']])) $s['budgets'][$v['idCharacter']]['closedAt'] ??= '2026-10-15';
        } elseif (str_contains($q, 'AS quoteDate')) {
            $this->rows = array_values(array_filter($s['quotes'] ?? [], static fn(array $row): bool => !isset($v['idCompany']) || (int) $row['idCompany'] === (int) $v['idCompany']));
        } elseif (str_contains($q, 'SELECT') && str_contains($q, 'FROM tblShareOffer')) {
            $this->rows = isset($s['offers'][$v['id']]) ? [$s['offers'][$v['id']]] : [];
            if (str_contains($q, 'SELECT idCompany FROM') && $this->rows) {
                $this->rows = [['idCompany' => $this->rows[0]['idCompany']]];
            }
        } elseif (str_contains($q, 'FROM tblCompanyShareTransaction')) {
            foreach ($s['transactions'] as $row) {
                if ((int) $row['idCompany'] !== (int) $v['idCompany']) continue;
                if (isset($v['counterparty'])) {
                    if ((int) $row['idCharacter'] !== (int) $v['idCharacter'] && (int) $row['idCounterpartyCharacter'] !== (int) $v['counterparty']) continue;
                } else {
                    if ((int) $row['idCharacter'] !== (int) $v['idCharacter']) continue;
                    if (isset($v['idTransaction']) ? (int) $row['id'] !== (int) $v['idTransaction'] || !in_array($row['operationCode'], ['buy_free', 'buy_from_holder', 'legacy_purchase'], true)
                        : $row['operationCode'] !== 'purchase_correction') continue;
                }
                $event = $s['events'][$row['idEvent']] ?? [];
                $this->rows[] = $row + ['eventTitle' => $event['title'] ?? null, 'eventDate' => $event['dateStart'] ?? null];
            }
        } elseif (str_contains($q, 'FROM tblCompanyFoundationSnapshot')) {
            $this->rows = isset($s['foundations'][$v['idCompany']]) ? [$s['foundations'][$v['idCompany']]] : [];
        } elseif (str_contains($q, 'UPDATE tblCompanyFoundationSnapshot')) {
            $foundation = &$s['foundations'][$v['idCompany']];
            $foundation['initialCompanyValue'] = $v['initialValue']; $foundation['initialShareCount'] = $v['initialCount'];
            foreach (['shareMarketPrice' => 'marketPrice', 'anonymousShareCount' => 'anonymousCount', 'stability' => 'stability', 'profitability' => 'profitability'] as $field => $param) {
                if ($v[$param] !== null) $foundation[$field] = $v[$param];
            }
        } elseif (str_contains($q, 'FROM tblCompanySnapshot') && str_contains($q, 'issuedShareCount IS NOT NULL')) {
            $this->rows = array_filter($s['snapshots'][$v['idCompany']] ?? [], static fn(array $row): bool => $row['issuedShareCount'] !== null) ? [['exists' => 1]] : [];
        } elseif (str_contains($q, 'FROM tblCompanySnapshot')) {
            $this->rows = str_contains($q, 'SELECT DISTINCT idEvent')
                ? array_map(static fn(int $id): array => ['idEvent' => $id], array_keys($s['snapshotPrices'][$v['idCompany']] ?? []))
                : (str_contains($q, 'SELECT shareMarketPrice')
                ? (isset($s['snapshotPrices'][$v['idCompany']][$v['idEvent']]) ? [['shareMarketPrice' => $s['snapshotPrices'][$v['idCompany']][$v['idEvent']]]] : [])
                : [['valid' => 1]]);
        } elseif (str_contains($q, 'UPDATE tblCompany SET initialCompanyValue')) {
            $company = &$s['companies'][$v['idCompany']];
            $company['initialCompanyValue'] = $v['initialValue']; $company['initialShareCount'] = $v['initialCount'];
            $company['issuedShareCount'] = $v['issued'];
            foreach (['companyValue', 'stability', 'profitability'] as $field) if (isset($v[$field])) $company[$field] = $v[$field];
        } elseif (str_contains($q, 'UPDATE tblCompany SET')) {
            $s['companies'][$v['id']]['companyValue'] = $v['value'];
        } elseif (str_contains($q, 'UPDATE tblShareOffer SET')) {
            $s['offers'][$v['idOffer']]['purchasedShareCount'] = $v['purchased'];
            $s['offers'][$v['idOffer']]['statusCode'] = $v['statusCode'];
        } else throw new RuntimeException('Unhandled test SQL: ' . $q);
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed { return array_shift($this->rows) ?? false; }
    public function fetchColumn(int $column = 0): mixed { $row = $this->fetch(); return $row === false ? false : array_values($row)[$column]; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function rowCount(): int { return 1; }
}
function purchaseCheck(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
function purchaseReject(PurchaseEventPdo $pdo, int $status, callable $operation): void
{
    $before = $pdo->state;
    try { $operation(); } catch (AetherShareException|AetherIdempotencyException $e) {
        purchaseCheck($e->getHttpStatus() === $status, 'Unexpected rejection: ' . $e->getMessage());
        purchaseCheck($pdo->state === $before, 'Rejected purchase changed bank, holdings or history'); return;
    }
    throw new RuntimeException('Purchase should have been rejected');
}
$today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Brussels')))->format('Y-m-d');
$tomorrow = (new DateTimeImmutable($today))->modify('+1 day')->format('Y-m-d');
$later = (new DateTimeImmutable($today))->modify('+20 days')->format('Y-m-d');
$events = [1 => ['id' => 1, 'title' => 'Past', 'dateStart' => '2000-01-01', 'dateEnd' => '2000-01-02'],
           2 => ['id' => 2, 'title' => 'Current', 'dateStart' => $today, 'dateEnd' => $today],
           3 => ['id' => 3, 'title' => 'Upcoming', 'dateStart' => $tomorrow, 'dateEnd' => $tomorrow],
           4 => ['id' => 4, 'title' => 'Later', 'dateStart' => $later, 'dateEnd' => $later]];
purchaseCheck(aetherShareChooseMarketEvent($events, $today)['id'] === 2, 'Current event takes priority');
purchaseCheck(aetherShareChooseMarketEvent([$events[1], $events[4], $events[3]], $today)['id'] === 3, 'Nearest future event takes priority');
purchaseCheck(aetherShareChooseMarketEvent([$events[1]], $today) === null, 'Past event is never participant default');
purchaseCheck(aetherShareChooseMarketEvent([['id' => 8, 'dateStart' => $today, 'dateEnd' => null]], $today)['id'] === 8, 'Missing end falls back to start date');
$participant = ['id' => 7, 'role' => 'participant']; $admin = ['id' => 8, 'role' => 'administrator']; $director = ['id' => 9, 'role' => 'director'];
$initial = ['events' => $events, 'companies' => [10 => ['id' => 10, 'initialShareCount' => 100,
    'issuedShareCount' => 100, 'companyValue' => '1000.00', 'isShareListed' => 1]],
    'characters' => [21 => ['id' => 21, 'idUser' => 7, 'type' => 'player', 'state' => 'active', 'bankaccount' => '500.00'],
                     22 => ['id' => 22, 'idUser' => 99, 'type' => 'player', 'state' => 'active', 'bankaccount' => '100.00']],
    'holdings' => [10 => [22 => 10]], 'transactions' => [], 'history' => [], 'status' => [], 'keys' => [],
    'snapshotPrices' => [10 => [1 => '12.50', 2 => '12.50', 3 => '12.50', 4 => '12.50']],
    'offers' => [30 => ['id' => 30, 'idCompany' => 10, 'sourceCode' => 'private_board', 'idOfferingCharacter' => null,
        'idEvent' => null, 'idSnapshot' => 1, 'statusCode' => 'open', 'expiresAt' => null,
        'offeredShareCount' => 20, 'purchasedShareCount' => 0, 'unitPrice' => '12.50']], 'recipients' => [30 => [21]]];
$input = ['idOffer' => 30, 'idCharacter' => 21, 'idEvent' => 2, 'shareCount' => 2];
foreach (['private_board', 'holder_sale', 'search'] as $source) {
    $pdo = new PurchaseEventPdo($initial); $pdo->state['offers'][30]['sourceCode'] = $source;
    if ($source === 'holder_sale') $pdo->state['offers'][30]['idOfferingCharacter'] = 22;
    if ($source === 'search') $pdo->state['offers'][30]['idEvent'] = 2;
    $response = aetherShareBuyOffer($pdo, $participant, $input, 'offer-' . $source);
    purchaseCheck($pdo->state['transactions'][0]['idEvent'] === 2, 'Every purchase type records the event');
    purchaseCheck($pdo->state['characters'][21]['bankaccount'] === '475.00', 'Normal purchase debits bank');
    purchaseCheck($response['idEvent'] === 2, 'Response records event');
    $once = $pdo->state;
    purchaseCheck(aetherShareBuyOffer($pdo, $participant, $input, 'offer-' . $source) === $response && $pdo->state === $once, 'Replay must not debit again');
    purchaseReject($pdo, $source === 'search' ? 409 : 403, fn() => aetherShareBuyOffer($pdo, $participant, [...$input, 'idEvent' => 1], 'past-' . $source));
    purchaseReject($pdo, $source === 'search' ? 409 : 403, fn() => aetherShareBuyOffer($pdo, $participant, [...$input, 'idEvent' => 4], 'future-' . $source));
}
$pdo = new PurchaseEventPdo($initial);
unset($pdo->state['events'][2]);
aetherShareBuyOffer($pdo, $participant, [...$input, 'idEvent' => 3], 'next-event');
purchaseCheck($pdo->state['transactions'][0]['idEvent'] === 3, 'Participants can buy at nearest upcoming event');
$pdo = new PurchaseEventPdo($initial);
purchaseReject($pdo, 409, fn() => aetherShareBuyOffer($pdo, $participant, [...$input, 'idEvent' => 0], 'missing-event'));
purchaseReject($pdo, 403, fn() => aetherShareBuyOffer($pdo, ['id' => 99, 'role' => 'participant'], $input, 'wrong-owner'));
$adminInput = ['idCompany' => 10, 'idCharacter' => 21, 'idEvent' => 1, 'shareCount' => 3, 'unitPrice' => '12.50'];
foreach ([$admin, $director] as $manager) {
    $pdo = new PurchaseEventPdo($initial); $pdo->state['characters'][21]['state'] = 'inactive';
    $response = aetherShareAddPurchase($pdo, $manager, $adminInput, 'historical-purchase');
    purchaseCheck($pdo->state['transactions'][0]['idEvent'] === 1, 'Historical purchase keeps chosen event');
    purchaseCheck($pdo->state['characters'][21]['bankaccount'] === '462.50', 'Historical purchase debits bank');
    purchaseCheck($pdo->state['companies'][10]['companyValue'] === '1037.50', 'Purchase funds company');
    purchaseCheck($pdo->state['holdings'][10][21] === 3, 'Purchase adds shares');
    $once = $pdo->state;
    purchaseCheck(aetherShareAddPurchase($pdo, $manager, $adminInput, 'historical-purchase') === $response && $pdo->state === $once, 'Historical replay must not debit again');
    purchaseReject($pdo, 409, fn() => aetherShareAddPurchase($pdo, $manager, [...$adminInput, 'shareCount' => 4], 'historical-purchase'));
}
$pdo = new PurchaseEventPdo($initial);
purchaseReject($pdo, 403, fn() => aetherShareAddPurchase($pdo, $participant, $adminInput, 'participant-grant'));
purchaseReject($pdo, 409, fn() => aetherShareAddPurchase($pdo, $admin, [...$adminInput, 'idEvent' => 999], 'missing-event'));
purchaseReject($pdo, 409, fn() => aetherShareAddPurchase($pdo, $admin, [...$adminInput, 'shareCount' => 91], 'insufficient-stock'));
purchaseReject($pdo, 409, fn() => aetherShareAddPurchase($pdo, $admin, [...$adminInput, 'unitPrice' => '200.00'], 'insufficient-bank'));
$draft = $initial;
$draft['characters'][21]['state'] = 'draft';
$draft['companies'][10]['isShareListed'] = 0;
$draft['quotes'] = [
    ['idCompany' => 10, 'companyName' => 'Company', 'idSnapshot' => 12, 'idEvent' => 2, 'unitPrice' => '100000.00'],
    ['idCompany' => 10, 'companyName' => 'Company', 'idSnapshot' => 11, 'idEvent' => 1, 'unitPrice' => '75000.00'],
    ['idCompany' => 10, 'companyName' => 'Company', 'idSnapshot' => 0, 'idEvent' => null, 'unitPrice' => '50000.00'],
];
$pdo = new PurchaseEventPdo($draft);
purchaseReject($pdo, 409, fn() => aetherShareBuyDraft($pdo, $participant, ['idCompany' => 10, 'idCharacter' => 21, 'shareCount' => 1], 'no-capital'));
aetherShareSetDraftLevel($pdo, 21, 1);
purchaseCheck($pdo->state['budgets'][21]['budgetAmount'] === '600000.00', 'Level 1 gives 600000 Fr capital');
purchaseCheck(aetherShareDraftCapital(3) === '2500000.00' && aetherShareDraftCapital(7) === '40000000.00', 'Draft capital follows the documented level table');
$draftInput = ['idCompany' => 10, 'idCharacter' => 21, 'shareCount' => 2, 'idSnapshot' => 12];
$response = aetherShareBuyDraft($pdo, $participant, $draftInput, 'draft-first');
purchaseCheck($pdo->state['characters'][21]['bankaccount'] === '500.00', 'Draft purchase must leave bank untouched');
purchaseCheck($pdo->state['budgets'][21]['spentAmount'] === '200000.00' && $response['remainingAmount'] === '400000.00', 'Draft purchase consumes capital at the exact snapshot quote');
purchaseCheck($pdo->state['companies'][10]['companyValue'] === '201000.00' && $pdo->state['holdings'][10][21] === 2, 'Draft purchase funds company and records ownership');
$once = $pdo->state;
purchaseCheck(aetherShareBuyDraft($pdo, $participant, $draftInput, 'draft-first') === $response && $pdo->state === $once, 'Draft replay cannot spend twice');
purchaseReject($pdo, 409, fn() => aetherShareBuyDraft($pdo, $participant, [...$draftInput, 'shareCount' => 5], 'over-budget'));
purchaseReject($pdo, 403, fn() => aetherShareBuyDraft($pdo, $participant, [...$draftInput, 'idSnapshot' => 11], 'historical-draft'));
purchaseReject($pdo, 403, fn() => aetherShareBuyDraft($pdo, ['id' => 99, 'role' => 'participant'], $draftInput, 'wrong-draft-owner'));
purchaseReject($pdo, 409, fn() => aetherShareSetDraftLevel($pdo, 21, 0));
aetherShareSetDraftLevel($pdo, 21, 2);
purchaseCheck(aetherShareDraftBudget($pdo, 21)['remainingAmount'] === '1000000.00', 'Raising rank preserves prior spending');
foreach ([$admin, $director] as $manager) {
    $history = aetherShareBuyDraft($pdo, $manager, [...$draftInput, 'shareCount' => 1, 'idSnapshot' => 11], 'draft-history-' . $manager['id']);
    purchaseCheck($history['totalAmount'] === '75000.00', 'Both manager roles buy at the selected historical quote');
}
aetherShareCloseDraftBudget($pdo, 21);
purchaseCheck(aetherShareDraftBudget($pdo, 21)['remainingAmount'] === '0.00', 'Unused capital expires when Draft ends');
purchaseReject($pdo, 409, fn() => aetherShareSetDraftLevel($pdo, 21, 3));
purchaseReject($pdo, 409, fn() => aetherShareBuyDraft($pdo, $participant, $draftInput, 'closed-capital'));
$pdo->state['characters'][21]['state'] = 'active';
purchaseReject($pdo, 409, fn() => aetherShareBuyDraft($pdo, $participant, $draftInput, 'not-draft'));
// Corrections keep separate purchases and immutable audit rows, changing only deltas.
$pdo = new PurchaseEventPdo($initial);
$first = aetherShareAddPurchase($pdo, $admin, $adminInput, 'correction-original');
$originalRow = $pdo->state['transactions'][0];
$edit = ['idCompany' => 10, 'idCharacter' => 21, 'idTransaction' => $first['transactionId'],
    'revision' => 0, 'shareCount' => 5, 'purchaseYear' => 1900, 'purchaseQuarter' => 1, 'adjustBank' => true];
$saved = aetherShareUpdatePurchase($pdo, $director, $edit, 'correct-with-bank');
purchaseCheck($pdo->state['holdings'][10][21] === 5 && $pdo->state['characters'][21]['bankaccount'] === '437.50', 'Increasing purchase debits only the added shares');
purchaseCheck($pdo->state['transactions'][0] === $originalRow, 'Original transaction is immutable');
$once = $pdo->state;
purchaseCheck(aetherShareUpdatePurchase($pdo, $director, $edit, 'correct-with-bank') === $saved && $pdo->state === $once, 'Correction replay cannot debit twice');
purchaseReject($pdo, 409, fn() => aetherShareUpdatePurchase($pdo, $director, [...$edit, 'shareCount' => 6], 'correct-with-bank'));
purchaseReject($pdo, 409, fn() => aetherShareUpdatePurchase($pdo, $director, $edit, 'stale-revision'));
$edit['revision'] = $saved['revision']; $edit['shareCount'] = 4; $edit['adjustBank'] = false;
$saved = aetherShareUpdatePurchase($pdo, $admin, $edit, 'correct-no-bank');
purchaseCheck($pdo->state['characters'][21]['bankaccount'] === '437.50' && $pdo->state['holdings'][10][21] === 4, 'Bank switch off preserves balance while correcting holdings');
purchaseCheck($pdo->state['companies'][10]['companyValue'] === '1050.00', 'Free-share correction updates company by only the corrected difference');
$currentPeriod = aetherShareQuarterFromDate($today);
purchaseCheck(aetherSharePurchasePeriod($pdo, [], $participant, 2) === ['idEvent' => 2] + $currentPeriod,
    'Participant purchases without a chosen period always use the current game quarter');
purchaseCheck(aetherSharePurchasePeriod($pdo, $currentPeriod, $participant, 2) === ['idEvent' => 2] + $currentPeriod,
    'The current quarter is accepted for participants');
foreach ([-1, 1] as $offset) {
    purchaseReject($pdo, 403, fn() => aetherSharePurchasePeriod($pdo,
        [...$currentPeriod, 'purchaseYear' => $currentPeriod['purchaseYear'] + $offset], $participant, 2));
}
purchaseCheck(aetherSharePurchasePeriod($pdo, [], $participant, null, false, '1873-04-17')['purchaseYear'] === $currentPeriod['purchaseYear'],
    'Draft and foundation purchases also use the current game quarter for participants');
$newEvent = aetherShareEventForQuarter($events, $currentPeriod['purchaseYear'], $currentPeriod['purchaseQuarter']);
$pdo->state['snapshotPrices'][10][$newEvent['id']] = '20.00';
$edit = array_merge($edit, $currentPeriod, ['revision' => $saved['revision'], 'adjustBank' => true]);
$saved = aetherShareUpdatePurchase($pdo, $director, $edit, 'move-quarter');
purchaseCheck($saved['purchase']['unitPrice'] === '20.00' && $saved['purchase']['idEvent'] === (int) $newEvent['id'], 'Moving quarter reprices from the next event snapshot');
purchaseCheck($pdo->state['characters'][21]['bankaccount'] === '407.50', 'Repricing applies only the 30 Fr price difference');
$effective = aetherSharePurchaseWithCorrections($originalRow, aetherSharePurchaseCorrections($pdo, 10, 21));
purchaseCheck((int) $effective['shareCount'] === 4 && $effective['totalAmount'] === '80.00' && $effective['revision'] === $saved['revision'], 'History overlays the latest correction once');
purchaseReject($pdo, 403, fn() => aetherShareUpdatePurchase($pdo, $participant, $edit, 'no-manager-edit'));
purchaseReject($pdo, 404, fn() => aetherShareUpdatePurchase($pdo, $director, [...$edit, 'idCharacter' => 22], 'wrong-purchase-owner'));
purchaseReject($pdo, 409, fn() => aetherShareUpdatePurchase($pdo, $director, [...$edit, 'revision' => $saved['revision'], 'shareCount' => 99], 'correction-stock'));
$pdo->state['holdings'][10][21] = 1; // Three shares were subsequently sold.
purchaseReject($pdo, 409, fn() => aetherShareUpdatePurchase($pdo, $director, [...$edit, 'revision' => $saved['revision'], 'shareCount' => 1], 'already-sold'));

// A correction to a transfer moves shares and (optionally) money on both sides.
$pdo = new PurchaseEventPdo($initial); $pdo->state['offers'][30]['sourceCode'] = 'holder_sale';
$pdo->state['offers'][30]['idOfferingCharacter'] = 22;
$transfer = aetherShareBuyOffer($pdo, $participant, $input, 'holder-original');
$transferPeriod = aetherShareQuarterFromDate($events[2]['dateStart']);
$transferEdit = ['idCompany' => 10, 'idCharacter' => 21, 'idTransaction' => $transfer['transactionId'],
    'revision' => 0, 'shareCount' => 4, 'adjustBank' => true] + $transferPeriod;
$saved = aetherShareUpdatePurchase($pdo, $admin, $transferEdit, 'holder-correction');
purchaseCheck($pdo->state['holdings'][10][21] === 4 && $pdo->state['holdings'][10][22] === 6, 'Transfer correction conserves issued shares');
purchaseCheck($pdo->state['characters'][21]['bankaccount'] === '450.00' && $pdo->state['characters'][22]['bankaccount'] === '150.00', 'Transfer bank correction balances buyer and seller');
purchaseCheck($pdo->state['companies'][10]['companyValue'] === '1000.00' && $pdo->state['offers'][30]['purchasedShareCount'] === 4, 'Transfer correction preserves company value and updates offer count');
$pdo->state['characters'][22]['bankaccount'] = '0.00';
purchaseReject($pdo, 409, fn() => aetherShareUpdatePurchase($pdo, $director, [...$transferEdit, 'revision' => $saved['revision'], 'shareCount' => 2], 'seller-cannot-refund'));
$pdo->state['characters'][21]['bankaccount'] = '0.00';
purchaseReject($pdo, 409, fn() => aetherShareUpdatePurchase($pdo, $director, [...$transferEdit, 'revision' => $saved['revision'], 'shareCount' => 5], 'buyer-cannot-pay'));

// New quarterly purchases get their price on the server, not from a browser field.
$pdo = new PurchaseEventPdo($initial); $pdo->state['snapshotPrices'][10][1] = '15.00';
$quarterBuy = ['idCompany' => 10, 'idCharacter' => 21, 'purchaseYear' => 1900, 'purchaseQuarter' => 1, 'shareCount' => 2, 'unitPrice' => '0.01'];
$bought = aetherShareAddPurchase($pdo, $admin, $quarterBuy, 'quarter-price');
purchaseCheck($bought['totalAmount'] === '30.00', 'Quarter purchase uses snapshot price even if browser submits a different price');
aetherShareAddPurchase($pdo, $admin, $quarterBuy, 'quarter-second-buy');
purchaseCheck($pdo->state['holdings'][10][21] === 4 && count($pdo->state['transactions']) === 2, 'Two purchases remain separate while holdings add up');
purchaseReject($pdo, 409, fn() => aetherShareAddPurchase($pdo, $admin, [...$quarterBuy, 'purchaseYear' => 9899], 'no-next-event'));
$pdo->state['companies'][10]['foundationDate'] = '1901-01-01';
purchaseReject($pdo, 422, fn() => aetherShareAddPurchase($pdo, $admin, $quarterBuy, 'before-company'));

// Historical quarter lookup skips events without a snapshot for this company.
$pdo = new PurchaseEventPdo($initial);
$pdo->state['events'] = [
    25 => ['id' => 25, 'title' => '1925 event', 'dateStart' => '2025-05-01', 'dateEnd' => '2025-05-02'],
    26 => ['id' => 26, 'title' => '1926 event', 'dateStart' => '2026-05-01', 'dateEnd' => '2026-05-02'],
    27 => ['id' => 27, 'title' => '1927 snapshot', 'dateStart' => '2027-05-01', 'dateEnd' => '2027-05-02'],
];
$pdo->state['snapshotPrices'] = [10 => [27 => '20.00'], 99 => [25 => '5.00', 26 => '6.00']];
$historical = [...$quarterBuy, 'purchaseYear' => 1925, 'purchaseQuarter' => 1];
$bought = aetherShareAddPurchase($pdo, $director, $historical, 'skip-empty-events');
purchaseCheck($pdo->state['transactions'][0]['idEvent'] === 27 && $bought['totalAmount'] === '40.00', 'A 1925 purchase uses the 1927 company snapshot despite intervening events and other-company snapshots');
$corrected = aetherShareUpdatePurchase($pdo, $admin, [
    'idCompany' => 10, 'idCharacter' => 21, 'idTransaction' => $bought['transactionId'], 'revision' => 0,
    'shareCount' => 3, 'purchaseYear' => 1926, 'purchaseQuarter' => 1, 'adjustBank' => false,
], 'correct-skip-empty-events');
purchaseCheck($corrected['purchase']['idEvent'] === 27 && $corrected['purchase']['unitPrice'] === '20.00', 'Purchase corrections use the same company-specific future snapshot lookup');
purchaseReject($pdo, 409, fn() => aetherShareAddPurchase($pdo, $director, [...$historical, 'purchaseYear' => 1927, 'purchaseQuarter' => 3], 'after-last-company-snapshot'));
purchaseReject($pdo, 409, fn() => aetherShareUpdatePurchase($pdo, $admin, [
    'idCompany' => 10, 'idCharacter' => 21, 'idTransaction' => $bought['transactionId'], 'revision' => $corrected['revision'],
    'shareCount' => 3, 'purchaseYear' => 1927, 'purchaseQuarter' => 3, 'adjustBank' => false,
], 'correct-after-last-company-snapshot'));
$pdo->state['snapshotPrices'][10][26] = '0.00';
purchaseReject($pdo, 409, fn() => aetherShareAddPurchase($pdo, $director, [...$historical, 'purchaseYear' => 1926], 'invalid-next-snapshot-price'));

// Draft history retains its funding source, including after leaving Draft.
$pdo = new PurchaseEventPdo($draft);
aetherShareSetDraftLevel($pdo, 21, 1);
$draftBought = aetherShareBuyDraft($pdo, $participant, [...$draftInput, 'shareCount' => 1], 'draft-to-correct');
aetherShareCloseDraftBudget($pdo, 21);
$pdo->state['characters'][21]['state'] = 'active';
$draftEdit = ['idCompany' => 10, 'idCharacter' => 21, 'idTransaction' => $draftBought['transactionId'],
    'revision' => 0, 'shareCount' => 2, 'adjustBank' => false] + aetherShareQuarterFromDate($events[2]['dateStart']);
$saved = aetherShareUpdatePurchase($pdo, $director, $draftEdit, 'draft-correction');
purchaseCheck($pdo->state['budgets'][21]['spentAmount'] === '200000.00' && $pdo->state['characters'][21]['bankaccount'] === '500.00', 'Draft correction adjusts capital without touching bank when switched off');
purchaseCheck($pdo->state['budgets'][21]['closedAt'] !== null, 'Historical Draft correction does not reopen expired capital');
purchaseReject($pdo, 409, fn() => aetherShareUpdatePurchase($pdo, $director, [...$draftEdit, 'revision' => $saved['revision'], 'shareCount' => 7], 'draft-capital-limit'));

$pdo = new PurchaseEventPdo($draft);
$pdo->state['companies'][10]['foundationDate'] = '1900-01-01';
aetherShareSetDraftLevel($pdo, 21, 1);
$foundationBought = aetherShareBuyDraft($pdo, $admin, ['idCompany' => 10, 'idCharacter' => 21, 'idSnapshot' => 0, 'shareCount' => 1], 'foundation-buy');
$foundationEdit = ['idCompany' => 10, 'idCharacter' => 21, 'idTransaction' => $foundationBought['transactionId'],
    'revision' => 0, 'shareCount' => 2, 'purchaseYear' => 1900, 'purchaseQuarter' => 1, 'adjustBank' => false];
$saved = aetherShareUpdatePurchase($pdo, $admin, $foundationEdit, 'foundation-correction');
purchaseCheck($saved['purchase']['idEvent'] === null && $saved['purchase']['unitPrice'] === '50000.00', 'Correcting founding purchase in its own quarter keeps the founding quote');

// Opening purchase history repairs legacy ownership metadata, without financial writes.
$legacy = $initial;
$legacy['characters'][21]['createdAt'] = '2024-05-16 12:34:56';
$legacy['characters'][22]['createdAt'] = '2020-02-02 11:00:00';
$legacy['holdings'][10][21] = 10;
$legacy['companies'][10]['foundationDate'] = '1925-01-01';
$historyInput = ['idCompany' => 10, 'idCharacter' => 21];
$pdo = new PurchaseEventPdo($legacy);
$initialized = aetherShareInitializePurchaseHistory($pdo, $director, $historyInput, 'legacy-history');
purchaseCheck($initialized['initializedPurchases'] === 1, 'Legacy holdings create a single historical purchase record');
$legacyRow = $pdo->state['transactions'][0];
$purchase = aetherSharePurchaseWithCorrections($legacyRow, []);
purchaseCheck($purchase['purchaseYear'] === 1924 && $purchase['purchaseQuarter'] === 2 && $purchase['purchaseDateSource'] === 'character_created', 'Legacy date uses the character creation quarter, exactly 100 years earlier');
purchaseCheck((int) $purchase['shareCount'] === 10 && $purchase['unitPrice'] === null, 'Keep the existing share count and do not invent an old price');
purchaseCheck($pdo->state['holdings'] === $legacy['holdings'] && $pdo->state['characters'] === $legacy['characters']
    && $pdo->state['companies'] === $legacy['companies'] && $pdo->state['history'] === [] && $pdo->state['status'] === [],
    'Initializing history does not change holdings, bank, company values or status');
$once = $pdo->state;
purchaseCheck(aetherShareInitializePurchaseHistory($pdo, $director, $historyInput, 'legacy-history') === $initialized && $pdo->state === $once,
    'Retry of history initialization is idempotent');
purchaseCheck(aetherShareInitializePurchaseHistory($pdo, $admin, $historyInput, 'legacy-reopen')['initializedPurchases'] === 0
    && count($pdo->state['transactions']) === 1, 'Reopening history with a new request ID cannot create duplicates');
purchaseReject($pdo, 403, fn() => aetherShareInitializePurchaseHistory($pdo, $participant, $historyInput, 'participant-backfill'));
$legacyEdit = $historyInput + ['idTransaction' => $legacyRow['id'], 'revision' => 0, 'shareCount' => 12,
    'purchaseYear' => 1924, 'purchaseQuarter' => 2, 'adjustBank' => false];
$updated = aetherShareUpdatePurchase($pdo, $admin, $legacyEdit, 'legacy-count-edit');
purchaseCheck($pdo->state['holdings'][10][21] === 12 && $pdo->state['characters'][21]['bankaccount'] === '500.00'
    && $pdo->state['companies'][10]['companyValue'] === '1000.00', 'Legacy purchase remains editable without fabricating a bank or capital amount');
purchaseReject($pdo, 409, fn() => aetherShareUpdatePurchase($pdo, $admin, [...$legacyEdit, 'revision' => $updated['revision'], 'adjustBank' => true], 'unknown-legacy-bank'));
purchaseCheck(aetherShareInitializePurchaseHistory($pdo, $admin, $historyInput, 'legacy-after-edit')['initializedPurchases'] === 0,
    'Correcting a reconstructed purchase does not cause another backfill');

$pdo = new PurchaseEventPdo($initial);
$pdo->state['characters'][21]['createdAt'] = '2024-05-16 12:34:56';
aetherShareAddPurchase($pdo, $admin, $adminInput, 'dated-buy-before-backfill');
$datedOriginal = $pdo->state['transactions'][0];
$pdo->state['holdings'][10][21] += 7;
aetherShareInitializePurchaseHistory($pdo, $admin, $historyInput, 'mixed-legacy-history');
purchaseCheck($pdo->state['transactions'][0] === $datedOriginal && $pdo->state['transactions'][1]['shareCount'] === 7,
    'Only undocumented legacy shares are reconstructed; newer purchases keep their original date and count');

$pdo = new PurchaseEventPdo($initial);
$pdo->state['characters'][21]['createdAt'] = '2024-05-16 12:34:56';
aetherShareAddPurchase($pdo, $admin, $adminInput, 'undated-buy');
$pdo->state['transactions'][0]['idEvent'] = null; $pdo->state['transactions'][0]['detailsJson'] = null;
$before = $pdo->state;
aetherShareInitializePurchaseHistory($pdo, $director, $historyInput, 'undated-history');
$repaired = aetherSharePurchaseWithCorrections($pdo->state['transactions'][0], aetherSharePurchaseCorrections($pdo, 10, 21));
purchaseCheck($repaired['purchaseYear'] === 1924 && $repaired['purchaseQuarter'] === 2
    && $repaired['unitPrice'] === $before['transactions'][0]['unitPrice'] && $repaired['shareCount'] === 3,
    'An undated existing purchase gets the creation quarter while preserving its price and count');
purchaseCheck($pdo->state['holdings'] === $before['holdings'] && $pdo->state['characters'] === $before['characters']
    && $pdo->state['companies'] === $before['companies'], 'Filling in a missing date has no financial side effects');
purchaseCheck(aetherShareInitializePurchaseHistory($pdo, $admin, $historyInput, 'undated-reopen')['initializedPurchases'] === 0, 'A repaired missing date is not repaired twice');

$pdo = new PurchaseEventPdo($initial); $pdo->state['characters'][22]['createdAt'] = '2020-02-02 11:00:00';
$pdo->state['offers'][30]['sourceCode'] = 'holder_sale'; $pdo->state['offers'][30]['idOfferingCharacter'] = 22;
$sold = aetherShareBuyOffer($pdo, $participant, [...$input, 'shareCount' => 4], 'legacy-sale');
aetherShareInitializePurchaseHistory($pdo, $admin, ['idCompany' => 10, 'idCharacter' => 22], 'legacy-seller-history');
purchaseCheck($pdo->state['transactions'][1]['shareCount'] === 10 && $pdo->state['holdings'][10][22] === 6,
    'Reconstruct historical ownership before later sales, without granting the already sold shares again');
aetherShareUpdatePurchase($pdo, $director, ['idCompany' => 10, 'idCharacter' => 21, 'idTransaction' => $sold['transactionId'],
    'revision' => 0, 'shareCount' => 2, 'adjustBank' => false] + aetherShareQuarterFromDate($events[2]['dateStart']), 'legacy-sale-corrected');
purchaseCheck(aetherShareInitializePurchaseHistory($pdo, $admin, ['idCompany' => 10, 'idCharacter' => 22], 'legacy-seller-reopen')['initializedPurchases'] === 0,
    'Corrected sales must be taken into account when checking for missing legacy ownership');

$pdo = new PurchaseEventPdo($initial); $pdo->state['holdings'][10][21] = 10;
purchaseReject($pdo, 409, fn() => aetherShareInitializePurchaseHistory($pdo, $admin, $historyInput, 'missing-character-created-date'));

// Purchases and their corrections cannot introduce values outside the recorded history.
$recordedState = $initial; $recordedState['recordedValues'][10] = '1000.00';
$pdo = new PurchaseEventPdo($recordedState);
$bought = aetherShareAddPurchase($pdo, $admin, $adminInput, 'recorded-manager-buy');
purchaseCheck($pdo->state['companies'][10]['companyValue'] === '1000.00'
    && $pdo->state['characters'][21]['bankaccount'] === '462.50' && $pdo->state['holdings'][10][21] === 3,
    'A manager purchase debits the bank and adds shares without raising a recorded company value');
$correctInput = ['idCompany' => 10, 'idCharacter' => 21, 'idTransaction' => $bought['transactionId'],
    'revision' => 0, 'shareCount' => 4, 'purchaseYear' => 1900, 'purchaseQuarter' => 1, 'adjustBank' => false];
$corrected = aetherShareUpdatePurchase($pdo, $director, $correctInput, 'recorded-correction-no-bank');
purchaseCheck($pdo->state['companies'][10]['companyValue'] === '1000.00'
    && $pdo->state['characters'][21]['bankaccount'] === '462.50' && $pdo->state['holdings'][10][21] === 4,
    'Changing a purchase cannot add an unrecorded capital delta, even with bank adjustment disabled');
$corrected = aetherShareUpdatePurchase($pdo, $admin, [...$correctInput, 'revision' => $corrected['revision'],
    'shareCount' => 2, 'adjustBank' => true], 'recorded-correction-with-bank');
purchaseCheck($pdo->state['companies'][10]['companyValue'] === '1000.00'
    && $pdo->state['characters'][21]['bankaccount'] === '487.50' && $pdo->state['holdings'][10][21] === 2,
    'The bank toggle still books the exact purchase difference while preserving the recorded company value');
$pdo->state['companies'][10]['companyValue'] = '1050.00';
aetherShareUpdatePurchase($pdo, $admin, [...$correctInput, 'revision' => $corrected['revision'],
    'shareCount' => 2], 'recorded-correction-old-value');
purchaseCheck($pdo->state['companies'][10]['companyValue'] === '1000.00', 'Saving an unchanged purchase also replaces a stale legacy company value with its recorded value');
$pdo = new PurchaseEventPdo($recordedState);
aetherShareBuyOffer($pdo, $participant, $input, 'recorded-offer-buy');
purchaseCheck($pdo->state['companies'][10]['companyValue'] === '1000.00'
    && $pdo->state['characters'][21]['bankaccount'] === '475.00', 'Market purchases use the same recorded-value guard');
$pdo = new PurchaseEventPdo($draft); $pdo->state['recordedValues'][10] = '1000.00';
aetherShareSetDraftLevel($pdo, 21, 1);
aetherShareBuyDraft($pdo, $participant, [...$draftInput, 'shareCount' => 1], 'recorded-draft-buy');
purchaseCheck($pdo->state['companies'][10]['companyValue'] === '1000.00'
    && $pdo->state['budgets'][21]['spentAmount'] === '100000.00', 'Draft purchases spend the agreed capital without raising a recorded company value');

// Founding corrections stop at the first explicit snapshot share count.
$foundationState = $initial;
$foundationState['companies'][10]['initialCompanyValue'] = '100.00';
$foundationState['companies'][10]['initialShareCount'] = 20;
$foundationState['foundations'][10] = ['initialCompanyValue' => '100.00', 'initialShareCount' => 20,
    'shareMarketPrice' => '5.00', 'anonymousShareCount' => 0];
$foundationState['snapshots'][10] = [11 => ['issuedShareCount' => 100], 12 => ['issuedShareCount' => null]];
$foundationInput = ['idCompany' => 10, 'initialCompanyValue' => '150.00', 'initialShareCount' => 10];
foreach ([$admin, $director] as $manager) {
    $pdo = new PurchaseEventPdo($foundationState);
    $before = $pdo->state;
    $first = aetherShareManageCompany($pdo, $manager, 'foundation', $foundationInput, 'foundation-20-to-10');
    $second = aetherShareManageCompany($pdo, $manager, 'foundation', [...$foundationInput, 'initialShareCount' => 1], 'foundation-10-to-1');
    purchaseCheck($first['stock']['issued'] === 100 && $second['stock']['issued'] === 100, 'Changing founding count 20 -> 10 -> 1 must not turn an explicit 100-share snapshot into 81 current shares');
    purchaseCheck($pdo->state['companies'][10]['initialShareCount'] === 1 && $pdo->state['foundations'][10]['initialShareCount'] === 1, 'The historical founding correction itself is still saved');
    purchaseCheck($pdo->state['companies'][10]['companyValue'] === $before['companies'][10]['companyValue']
        && $pdo->state['snapshots'] === $before['snapshots'] && $pdo->state['characters'] === $before['characters']
        && $pdo->state['holdings'] === $before['holdings'] && $pdo->state['status'] === $before['status'], 'A bounded founding correction preserves later snapshots, current value, holdings, banks and status');
    $once = $pdo->state;
    purchaseCheck(aetherShareManageCompany($pdo, $manager, 'foundation', [...$foundationInput, 'initialShareCount' => 1], 'foundation-10-to-1') === $second
        && $pdo->state === $once, 'Replaying a founding correction does not apply it twice');
}
$pdo = new PurchaseEventPdo($foundationState); $pdo->state['companies'][10]['issuedShareCount'] = 95;
$result = aetherShareManageCompany($pdo, $director, 'foundation', $foundationInput, 'foundation-after-buyback');
purchaseCheck($result['stock']['issued'] === 95, 'Legitimate later buybacks must not be reset to the snapshot count');
$pdo = new PurchaseEventPdo($foundationState); $pdo->state['companies'][10]['issuedShareCount'] = 20;
$pdo->state['snapshots'][10] = [11 => ['issuedShareCount' => null]];
$result = aetherShareManageCompany($pdo, $director, 'foundation', [...$foundationInput, 'initialShareCount' => 40], 'foundation-null-snapshot');
purchaseCheck($result['stock']['issued'] === 40 && $pdo->state['companies'][10]['companyValue'] === '1000.00', 'Legacy snapshots without an explicit share count inherit the corrected founding count');
$pdo = new PurchaseEventPdo($foundationState); $pdo->state['companies'][10]['issuedShareCount'] = 20;
$pdo->state['snapshots'][10] = [];
$result = aetherShareManageCompany($pdo, $director, 'foundation', [...$foundationInput, 'initialShareCount' => 40], 'foundation-no-snapshot');
purchaseCheck($result['stock']['issued'] === 40 && $pdo->state['companies'][10]['companyValue'] === '150.00', 'Without snapshots the founding count and value still determine current values');
purchaseReject($pdo, 409, fn() => aetherShareManageCompany($pdo, $director, 'foundation', [...$foundationInput, 'initialShareCount' => 1], 'foundation-below-holdings'));

echo "Share purchases, quarterly dates, corrections, bounded founding counts, bank deltas, permissions, rollback and idempotency tests passed.\n";
