<?php
declare(strict_types=1);

// Exercise the actual timeline builder with an isolated data source, without a live database.
$fixture = sys_get_temp_dir() . '/aether-quarter-range-' . bin2hex(random_bytes(5));
mkdir($fixture, 0777, true);
foreach (['companyQuarterMath.php', 'companyQuarterService.php'] as $file) {
    copy(__DIR__ . '/../api/companies/' . $file, $fixture . '/' . $file);
}
file_put_contents($fixture . '/companyUtils.php', <<<'PHP'
<?php
function dbOne(PDO $pdo, string $sql, array $params): ?array { return $pdo->company; }
function getCompanyFoundationSnapshot(PDO $pdo, int $id): array { return $pdo->foundation; }
PHP);
require $fixture . '/companyQuarterService.php';

final class QuarterTimelinePdo extends PDO
{
    public array $company;
    public array $foundation;
    public array $events = [];
    public array $snapshots = [];
    public function __construct(string $foundationDate)
    {
        $this->company = ['id' => 1, 'foundationDate' => $foundationDate, 'companyValue' => 100,
            'issuedShareCount' => 100, 'stability' => 0, 'profitability' => 0];
        $this->foundation = ['foundationDate' => $foundationDate, 'initialCompanyValue' => 100,
            'initialShareCount' => 100, 'shareMarketPrice' => 1, 'stability' => 0, 'profitability' => 0];
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return new QuarterTimelineStatement($this, $query);
    }
}
final class QuarterTimelineStatement extends PDOStatement
{
    private array $rows = [];
    public function __construct(private QuarterTimelinePdo $pdo, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        if (str_contains($this->sql, 'FROM tblCompanySnapshot')) {
            $this->rows = $this->pdo->snapshots;
        } elseif (str_contains($this->sql, 'FROM tblCompanyQuarter')) {
            $this->rows = [];
        } elseif (str_contains($this->sql, 'FROM tblEvent')) {
            $this->rows = array_values(array_filter($this->pdo->events,
                static fn(array $event): bool => $event['dateStart'] >= $params['today']));
            usort($this->rows, static fn(array $a, array $b): int => $a['dateStart'] <=> $b['dateStart']);
            $this->rows = array_slice($this->rows, 0, 1);
        } else {
            throw new RuntimeException('Unhandled timeline SQL: ' . $this->sql);
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed
    {
        return array_shift($this->rows) ?? false;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
}
function timelineCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$today = new DateTimeImmutable('today', new DateTimeZone('Europe/Brussels'));
$currentIndex = aetherCompanyQuarterForOutgameDate($today->format('Y-m-d'));
$pdo = new QuarterTimelinePdo(((int) $today->format('Y') - 101) . '-01-01');
$rows = aetherCompanyQuarterTimeline($pdo, 1);
$last = $rows[count($rows) - 1];
timelineCheck(aetherCompanyQuarterIndex($last['year'], $last['quarter']) === $currentIndex,
    'Without an upcoming event, the actual timeline must reach the current game quarter.');

$nextEvent = ['dateStart' => $today->modify('+4 months')->format('Y-m-d'), 'dateEnd' => null];
$pdo->events = [$nextEvent, ['dateStart' => $today->modify('+3 years')->format('Y-m-d'), 'dateEnd' => null]];
$rows = aetherCompanyQuarterTimeline($pdo, 1);
$last = $rows[count($rows) - 1];
timelineCheck(aetherCompanyQuarterIndex($last['year'], $last['quarter']) === aetherCompanyQuarterForOutgameDate($nextEvent['dateStart'])
    && $last['source'] === 'lineair', 'Include the next missing snapshot position, without extending to every unsaved future event.');

$futureYear = (int) $today->format('Y') + 2;
$pdo->snapshots = [['id' => 5, 'idEvent' => 10, 'title' => 'Future snapshot', 'dateStart' => "$futureYear-03-31",
    'dateEnd' => "$futureYear-04-01", 'companyValue' => 200, 'issuedShareCount' => 100,
    'shareMarketPrice' => 2, 'stability' => 0, 'profitability' => 0]];
$rows = aetherCompanyQuarterTimeline($pdo, 1);
$last = $rows[count($rows) - 1];
timelineCheck($last['year'] === $futureYear - 100 && $last['quarter'] === 2
    && $last['source'] === 'snapshot' && (float) $last['companyValue'] === 200.0,
    'The saved future snapshot must appear with its actual figures, including its last quarter.');
$current = array_values(array_filter($rows, static fn(array $row): bool =>
    aetherCompanyQuarterIndex($row['year'], $row['quarter']) === $currentIndex))[0];
timelineCheck($current['companyValue'] > 100 && $current['companyValue'] < 200,
    'Intermediate quarters must interpolate toward the saved future snapshot.');

echo "Company quarter timeline includes today, next event and saved future snapshots.\n";
