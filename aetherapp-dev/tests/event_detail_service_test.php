<?php
declare(strict_types=1);
require_once __DIR__ . '/../api/events/eventDetailService.php';

final class DetailStatement extends PDOStatement
{
    private array $params = [];
    public function __construct(private DetailPDO $pdo, private string $sql) {}
    public function execute(?array $params = null): bool
    {
        $this->params = $params ?? [];
        if (str_starts_with($this->sql, 'INSERT INTO tblLinkEventCharacter')) {
            $this->pdo->linked[$this->params['idCharacter']] = true;
            $this->pdo->writes++;
        }
        if (str_starts_with($this->sql, 'DELETE FROM tblLinkEventCharacter')) {
            unset($this->pdo->linked[$this->params['idCharacter']]);
            $this->pdo->writes++;
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if (str_contains($this->sql, 'FROM tblLinkEventCharacter l JOIN')) return $this->params['idEvent'] === 7 && isset($this->pdo->linked[$this->params['idCharacter']]) ? ['firstName' => 'Ada', 'lastName' => 'A', 'eventTitle' => 'Event'] : false;
        if (str_contains($this->sql, 'FROM tblCharacterDiary')) return ['gossip1' => '<script>plain</script>', 'gossip2' => 'Two', 'gossip3' => 'Three'];
        if (str_contains($this->sql, 'FROM tblCharacterEconomySnapshot')) return $this->params['idCharacter'] === 1 ? ['amount' => '123.45', 'transactionDate' => '2026-09-01'] : false;
        if (str_contains($this->sql, 'FROM tblEvent')) return ($this->params['id'] ?? 0) === 7 ? ['id' => 7, 'title' => 'Event'] : false;
        return $this->pdo->characters[$this->params['id']] ?? false;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        if (str_contains($this->sql, 'FROM tblCharacterEconomySnapshot')) {
            return $this->params['idEvent'] === 7 ? [['idCharacter' => 1]] : [];
        }
        if (str_contains($this->sql, 'FROM tblCharacterDiary')) {
            return [
                ['idCharacter' => 1, 'gossip1' => '<p>Gossip</p>', 'gossip2' => 'Level 2', 'gossip3' => '<p>&nbsp;<br></p>'],
                ['idCharacter' => 4, 'gossip1' => '<p>Level 1</p>', 'gossip2' => 'Level 2', 'gossip3' => 'Level 3'],
            ];
        }
        $rows = [];
        foreach ($this->pdo->characters as $character) {
            $character['linked'] = isset($this->pdo->linked[$character['id']]);
            $rows[] = $character;
        }
        return $rows;
    }
}
final class DetailPDO extends PDO
{
    public array $characters = [
        1 => ['id' => 1, 'idUser' => 10, 'type' => 'player', 'state' => 'active', 'firstName' => 'Ada', 'lastName' => 'A'],
        2 => ['id' => 2, 'idUser' => 20, 'type' => 'extra', 'state' => 'active', 'firstName' => 'Bob', 'lastName' => 'B'],
        3 => ['id' => 3, 'idUser' => 10, 'type' => 'extra', 'state' => 'active', 'firstName' => 'C', 'lastName' => 'C'],
        4 => ['id' => 4, 'idUser' => 20, 'type' => 'player', 'state' => 'active', 'firstName' => 'D', 'lastName' => 'D'],
        5 => ['id' => 5, 'idUser' => 10, 'type' => 'player', 'state' => 'draft', 'firstName' => 'Draft', 'lastName' => 'E'],
        6 => ['id' => 6, 'idUser' => 10, 'type' => 'player', 'state' => 'inactive', 'firstName' => 'Own', 'lastName' => 'F'],
        7 => ['id' => 7, 'idUser' => 20, 'type' => 'player', 'state' => 'active', 'firstName' => 'Other', 'lastName' => 'G'],
        8 => ['id' => 8, 'idUser' => 10, 'type' => 'extra', 'state' => 'active', 'firstName' => 'Extra', 'lastName' => 'H'],
    ];
    public array $linked = [1 => true, 2 => true, 3 => true, 4 => true];
    public int $writes = 0;
    private bool $transaction = false;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new DetailStatement($this, $query); }
    public function beginTransaction(): bool { $this->transaction = true; return true; }
    public function commit(): bool { $this->transaction = false; return true; }
    public function rollBack(): bool { $this->transaction = false; return true; }
    public function inTransaction(): bool { return $this->transaction; }
}
function detailAssert(bool $value, string $message): void { if (!$value) throw new RuntimeException($message); }
$pdo = new DetailPDO();
$manager = ['id' => 30, 'role' => 'administrator'];
$participant = ['id' => 10, 'role' => 'participant'];
$details = aetherGetEventCharacterData($pdo, $manager, 7, 1, 'gossip');
detailAssert($details['details']['gossip1'] === '<script>plain</script>' && $pdo->writes === 0, 'Modalread moet ongewijzigde tekst geven zonder writes');
detailAssert(aetherGetEventCharacterData($pdo, ['role' => 'director'], 7, 1, 'snapshot')['details']['amount'] === '123.45', 'Director kan snapshot lezen');
detailAssert(aetherGetEventCharacterData($pdo, $participant, 7, 1, 'gossip')['details']['gossip2'] === 'Two', 'Eigen gossip moet leesbaar zijn');
detailAssert(aetherGetEventCharacterData($pdo, $participant, 7, 1, 'snapshot')['details']['amount'] === '123.45', 'Eigen snapshot moet leesbaar zijn');
detailAssert(aetherGetEventCharacterData($pdo, $participant, 7, 3, 'gossip')['details']['gossip2'] === 'Two', 'Eigen gekoppelde figurant mag gossip bekijken');
$missing = aetherGetEventCharacterData($pdo, $participant, 7, 3, 'snapshot');
detailAssert($missing['details'] === null && str_contains($missing['message'], 'Financiële toestand nog niet aangepast voor "Event"'), 'Ontbrekende snapshot moet een veilige melding met de eventnaam geven');
foreach (['gossip', 'snapshot'] as $kind) {
    try { aetherGetEventCharacterData($pdo, $participant, 7, 4, $kind); throw new RuntimeException('Participant las gegevens van een ander'); }
    catch (AetherEventException $e) { detailAssert($e->getHttpStatus() === 403, 'Modalread moet eigenaarschap eisen'); }
}
try { aetherGetEventCharacterData($pdo, $participant, 7, 6, 'gossip'); throw new RuntimeException('Ongekoppeld personage gelezen'); }
catch (AetherEventException $e) { detailAssert($e->getHttpStatus() === 404, 'Eigen personage moet aan het event gekoppeld zijn voor een modalread'); }
try { aetherGetEventCharacterData($pdo, $manager, 8, 1, 'snapshot'); throw new RuntimeException('Gegevens ander event gelezen'); }
catch (AetherEventException $e) { detailAssert($e->getHttpStatus() === 404, 'Verkeerd event moet worden geweigerd'); }
$all = aetherGetEventDetails($pdo, $manager, 7);
detailAssert(count($all['characters']) === 8 && $all['canManage'], 'Admin moet beide groepen zien');
detailAssert($all['characters'][0]['hasSnapshot'] && !$all['characters'][0]['gossipComplete'], 'Lege gossipopmaak mag niet als ingevuld tellen');
detailAssert(!$all['characters'][3]['hasSnapshot'] && $all['characters'][3]['gossipComplete'], 'Compleet gossip en ontbrekende snapshot moeten apart worden gemeld');
$own = aetherGetEventDetails($pdo, $participant, 7);
$ownRows = array_column($own['characters'], null, 'id');
detailAssert(count($ownRows) === 4 && $ownRows[1]['canOpen'] && !$ownRows[4]['canOpen'], 'Alleen eigen personages moeten aanklikbaar zijn');
detailAssert($ownRows[1]['hasSnapshot'] && !$ownRows[1]['gossipComplete'] && $ownRows[3]['canViewStatus'], 'Status van eigen gekoppelde personages moet zichtbaar zijn');
detailAssert(!isset($ownRows[2], $ownRows[5], $ownRows[7], $ownRows[8]) && $ownRows[6]['canLink'], 'Alleen eigen spelers buiten Draft mogen koppelkeuzes zijn');
foreach ($own['characters'] as $character) {
    detailAssert(!array_key_exists('idUser', $character) && !array_key_exists('gossip1', $character)
        && ($character['canViewStatus'] || !array_key_exists('hasSnapshot', $character)), 'Statusinformatie van anderen mag niet aan deelnemers worden getoond');
}
aetherSetEventCharacter($pdo, $participant, 7, 6, true);
aetherSetEventCharacter($pdo, $participant, 7, 6, true);
detailAssert(isset($pdo->linked[6]) && count($pdo->linked) === 5, 'Eigen niet-Draft-speler moet idempotent gekoppeld kunnen worden');
foreach ([5, 7, 8] as $forbiddenId) {
    $writes = $pdo->writes;
    try { aetherSetEventCharacter($pdo, $participant, 7, $forbiddenId, true); throw new RuntimeException('Onbevoegd personage gekoppeld'); }
    catch (AetherEventException $e) { detailAssert($e->getHttpStatus() === 403 && $writes === $pdo->writes && !$pdo->inTransaction(), 'Draft, andere eigenaar en figurant moeten worden geweigerd'); }
}
aetherSetEventCharacter($pdo, $manager, 7, 2, false);
detailAssert(!isset($pdo->linked[2]), 'Ontkoppelen werkt niet');
aetherSetEventCharacter($pdo, ['id' => 20, 'role' => 'director'], 7, 2, true);
aetherSetEventCharacter($pdo, $manager, 7, 2, true);
detailAssert(isset($pdo->linked[2]) && count($pdo->linked) === 5, 'Dubbel koppelen maakt duplicaten');
$before = $pdo->writes;
try { aetherSetEventCharacter($pdo, $participant, 7, 2, false); throw new RuntimeException('Participant mocht koppelen'); }
catch (AetherEventException $e) { detailAssert($e->getHttpStatus() === 403 && $pdo->writes === $before, 'Ongeautoriseerde write'); }
try { aetherSetEventCharacter($pdo, $manager, 7, 999, true); throw new RuntimeException('Onbekend personage aanvaard'); }
catch (AetherEventException $e) { detailAssert($e->getHttpStatus() === 404 && !$pdo->inTransaction(), 'Onbekend personage moet rollback geven'); }
echo "Event detail service checks passed.\n";
