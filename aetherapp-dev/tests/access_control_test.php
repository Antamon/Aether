<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/characters/characterAccess.php';

$failures = [];

function assertAccess(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

final class AccessControlTestStatement extends PDOStatement
{
    private array $parameters = [];

    /** @param callable(array): mixed $result */
    public function __construct(private $result)
    {
    }

    public function execute(?array $params = null): bool
    {
        $this->parameters = $params ?? [];
        return true;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return ($this->result)($this->parameters);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        $result = ($this->result)($this->parameters);
        return is_array($result) ? reset($result) : $result;
    }
}

final class AccessControlTestPdo extends PDO
{
    /** @param array<int, array<string, mixed>> $users @param array<int, string> $skills */
    public function __construct(private array $users, private array $skills)
    {
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'FROM tblUser')) {
            return new AccessControlTestStatement(
                fn(array $params): mixed => $this->users[(int) ($params['id'] ?? 0)] ?? false
            );
        }

        if (str_contains($query, 'FROM tblSkill')) {
            return new AccessControlTestStatement(
                fn(array $params): mixed => $this->skills[(int) ($params['id'] ?? 0)] ?? false
            );
        }

        throw new RuntimeException('Onverwachte query in access-controltest.');
    }
}

$participantA = ['id' => 10, 'role' => AETHER_ROLE_PARTICIPANT];
$participantB = ['id' => 20, 'role' => AETHER_ROLE_PARTICIPANT];
$director = ['id' => 30, 'role' => AETHER_ROLE_DIRECTOR];
$administrator = ['id' => 40, 'role' => AETHER_ROLE_ADMINISTRATOR];

$ownPlayerDraft = ['id' => 1, 'idUser' => 10, 'type' => 'player', 'state' => 'draft'];
$ownPlayerActive = ['id' => 2, 'idUser' => 10, 'type' => 'player', 'state' => 'active'];
$ownExtra = ['id' => 3, 'idUser' => 10, 'type' => 'extra', 'state' => 'active'];
$otherPlayer = ['id' => 4, 'idUser' => 20, 'type' => 'player', 'state' => 'draft'];

assertAccess(aetherCanViewCharacter($participantA, $ownPlayerDraft), 'Participant moet eigen speler kunnen lezen.');
assertAccess(aetherCanViewCharacter($participantA, $ownExtra), 'Participant moet eigen figurant kunnen lezen.');
assertAccess(!aetherCanViewCharacter($participantA, $otherPlayer), 'Participant mag personage van een ander niet lezen.');
assertAccess(!aetherCanViewCharacter($participantB, $ownPlayerDraft), 'Een gemanipuleerd eigenaar-ID mag geen leestoegang geven.');

assertAccess(aetherCanEditCharacter($participantA, $ownPlayerActive), 'Participant moet eigen speler kunnen bewerken.');
assertAccess(!aetherCanEditCharacter($participantA, $ownExtra), 'Participant mag algemene gegevens van eigen figurant niet bewerken.');
assertAccess(!aetherCanEditCharacter($participantA, $otherPlayer), 'Participant mag speler van een ander niet bewerken.');
assertAccess(aetherCanEditDraftCharacter($participantA, $ownPlayerDraft), 'Participant moet draftvelden van eigen speler kunnen bewerken.');
assertAccess(!aetherCanEditDraftCharacter($participantA, $ownPlayerActive), 'Participant mag draftvelden van actieve speler niet bewerken.');
assertAccess(aetherCanEditCharacterDiaryAchievements($participantA, $ownExtra), 'Participant moet prestaties van eigen figurant kunnen bewerken.');

foreach ([$director, $administrator] as $privilegedUser) {
    assertAccess(aetherCanViewCharacter($privilegedUser, $otherPlayer), 'Beheerrol moet alle personages kunnen lezen.');
    assertAccess(aetherCanEditCharacter($privilegedUser, $otherPlayer), 'Beheerrol moet alle personages kunnen bewerken.');
    assertAccess(aetherCanEditDraftCharacter($privilegedUser, $ownPlayerActive), 'Beheerrol behoudt uitgebreide bewerkrechten.');
    assertAccess(aetherCanChangeCharacterAuthorityField($privilegedUser), 'Beheerrol moet eigenaar/type/status kunnen beheren.');
}

assertAccess(!aetherCanChangeCharacterAuthorityField($participantA), 'Participant mag eigenaar/type/status niet beheren.');
assertAccess(!aetherIsPrivilegedRole('administrator '), 'Een afwijkende of vervalste rol mag geen rechten geven.');
assertAccess(!aetherIsKnownRole('admin'), 'Alleen interne rolbenamingen zijn geldig.');

$policyPdo = new AccessControlTestPdo([
    10 => [
        'id' => 10,
        'username' => 'participant-a',
        'firstName' => 'Part',
        'lastName' => 'A',
        'role' => AETHER_ROLE_PARTICIPANT,
    ],
], [1 => 'public', 2 => 'secret']);
assertAccess(aetherCanManageSkill($policyPdo, $participantA, 1), 'Participant moet een publieke skill kunnen gebruiken.');
assertAccess(!aetherCanManageSkill($policyPdo, $participantA, 2), 'Participant mag een geheime skill niet beheren.');
assertAccess(aetherCanManageSkill($policyPdo, $director, 2), 'Director moet een geheime skill kunnen beheren.');
assertAccess(aetherCanManageSkill($policyPdo, $administrator, 2), 'Administrator moet een geheime skill kunnen beheren.');

aetherStartSession();
$_SESSION = ['user' => ['id' => 10, 'role' => AETHER_ROLE_ADMINISTRATOR]];
$loadedUser = aetherLoadAuthenticatedUser($policyPdo);
assertAccess(
    ($loadedUser['role'] ?? null) === AETHER_ROLE_PARTICIPANT,
    'Een vervalste sessierol mag de actuele databaserol niet vervangen.'
);

$deniedMutation = ['class' => 'scholar'];
if (aetherCanEditCharacter($participantA, $otherPlayer)) {
    $deniedMutation['class'] = 'tampered';
}
assertAccess(
    $deniedMutation['class'] === 'scholar',
    'Een geweigerde characteractie mag geen wijziging veroorzaken.'
);

assertAccess(aetherCsrfTokenMatches('same-token', 'same-token'), 'Geldig CSRF-token moet worden geaccepteerd.');
assertAccess(!aetherCsrfTokenMatches('forged-token', 'same-token'), 'Vervalst CSRF-token moet worden geweigerd.');
assertAccess(!aetherCsrfTokenMatches(null, 'same-token'), 'Ontbrekend CSRF-token moet worden geweigerd.');

$_SESSION = [];

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Access-control decision tests passed.\n";
