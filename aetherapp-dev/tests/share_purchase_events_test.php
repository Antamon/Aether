<?php
declare(strict_types=1);

// Exercise the real purchase services and idempotency layer with isolated storage.
$appRoot = $argv[1] ?? dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/aether-purchase-events-' . bin2hex(random_bytes(5));
foreach (['shares', 'shared', 'characters', 'companies'] as $folder) mkdir($fixture . '/api/' . $folder, 0777, true);
foreach (['shares/shareService.php', 'shares/shareMarketEvent.php', 'shares/shareException.php', 'shares/shareDraftService.php', 'shares/shareDraftBudget.php',
          'shares/shareRules.php', 'shared/decimal.php', 'shared/idempotency.php', 'shared/response.php'] as $file) {
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
function aetherShareStock(PDO $p, int $id, int $issued): array { return ['free' => $issued - array_sum($p->state['holdings'][$id] ?? [])]; }
function aetherShareLockOffer(PDO $p, int $id): ?array { return $p->state['offers'][$id] ?? null; }
function aetherShareOfferRecipient(PDO $p, int $id, int $c): bool { return in_array($c, $p->state['recipients'][$id] ?? [], true); }
function aetherShareAddHolding(PDO $p, int $co, int $c, int $n): void { $p->state['holdings'][$co][$c] = ($p->state['holdings'][$co][$c] ?? 0) + $n; }
function aetherShareLockHolding(PDO $p, int $co, int $c): ?array { return isset($p->state['holdings'][$co][$c]) ? ['shareCount' => $p->state['holdings'][$co][$c]] : null; }
function aetherShareRemoveHolding(PDO $p, int $co, int $c, int $old, int $n): void { $p->state['holdings'][$co][$c] -= $n; }
function aetherShareInsertTransaction(PDO $p, array $row): int { $p->state['transactions'][] = $row; return count($p->state['transactions']); }
function aetherShareInsertValueHistory(PDO $p, int $co, ?int $tx, int $u, string $reason, string $before, string $after, ?string $details = null): void { $p->state['history'][] = [$tx, $before, $after]; }
function aetherSharePortfolioValue(PDO $p, int $c): string { return '0.00'; }
function aetherShareSaveStatus(PDO $p, int $c, string $v, array $status): void { $p->state['status'][$c] = $status; }
PHP);
file_put_contents($fixture . '/api/characters/characterFinanceRepository.php', <<<'PHP'
<?php
function aetherFinanceLockCharacters(PDO $p, array $ids): array { return array_intersect_key($p->state['characters'], array_flip($ids)); }
function aetherFinanceAdjustBankBalance(PDO $p, int $id, string $delta): void { $p->state['characters'][$id]['bankaccount'] = aetherDecimalAdd($p->state['characters'][$id]['bankaccount'], $delta); }
PHP);
foreach (['companyLeadershipRules.php', 'companyUtils.php'] as $file) file_put_contents($fixture . '/api/companies/' . $file, '<?php');
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
        if (str_contains($q, 'INSERT INTO tblApiIdempotency')) {
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
        } elseif (str_contains($q, 'SELECT') && str_contains($q, 'FROM tblCharacterShareDraftBudget')) {
            $this->rows = isset($s['budgets'][$v['idCharacter']]) ? [$s['budgets'][$v['idCharacter']]] : [];
        } elseif (str_contains($q, 'INSERT INTO tblCharacterShareDraftBudget')) {
            $old = $s['budgets'][$v['idCharacter']] ?? ['spentAmount' => '0.00', 'closedAt' => null];
            $s['budgets'][$v['idCharacter']] = array_merge($old, ['purchasedLevel' => $v['level'], 'budgetAmount' => $v['amount']]);
        } elseif (str_contains($q, 'DELETE FROM tblCharacterShareDraftBudget')) {
            unset($s['budgets'][$v['idCharacter']]);
        } elseif (str_contains($q, 'UPDATE tblCharacterShareDraftBudget SET spentAmount')) {
            $s['budgets'][$v['idCharacter']]['spentAmount'] = aetherDecimalAdd($s['budgets'][$v['idCharacter']]['spentAmount'], $v['amount']);
        } elseif (str_contains($q, 'UPDATE tblCharacterShareDraftBudget SET closedAt')) {
            if (isset($s['budgets'][$v['idCharacter']])) $s['budgets'][$v['idCharacter']]['closedAt'] ??= '2026-10-15';
        } elseif (str_contains($q, 'AS quoteDate')) {
            $this->rows = array_values(array_filter($s['quotes'] ?? [], static fn(array $row): bool => !isset($v['idCompany']) || (int) $row['idCompany'] === (int) $v['idCompany']));
        } elseif (str_contains($q, 'SELECT') && str_contains($q, 'FROM tblShareOffer')) {
            $this->rows = isset($s['offers'][$v['id']]) ? [$s['offers'][$v['id']]] : [];
            if (str_contains($q, 'SELECT idCompany FROM') && $this->rows) {
                $this->rows = [['idCompany' => $this->rows[0]['idCompany']]];
            }
        } elseif (str_contains($q, 'FROM tblCompanySnapshot')) {
            $this->rows = [['valid' => 1]];
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
echo "Share event selection, purchase permissions, bank debits, rollback and idempotency tests passed.\n";
