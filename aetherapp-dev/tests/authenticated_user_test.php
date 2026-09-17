<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/characters/characterAccess.php';

$failures = [];

function assertAuthenticatedUserTest(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    fwrite(STDERR, "SKIP: PDO SQLite is niet beschikbaar.\n");
    exit(2);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec(
    'CREATE TABLE tblUser (
        id INTEGER PRIMARY KEY,
        username TEXT NOT NULL,
        firstName TEXT NOT NULL,
        lastName TEXT NOT NULL,
        role TEXT NOT NULL
    )'
);
$pdo->exec(
    'CREATE TABLE tblCharacter (
        id INTEGER PRIMARY KEY,
        idUser INTEGER,
        type TEXT NOT NULL,
        state TEXT NOT NULL,
        class TEXT
    )'
);
$pdo->exec('CREATE TABLE tblSkill (id INTEGER PRIMARY KEY, visibility TEXT NOT NULL)');

$insertUser = $pdo->prepare(
    'INSERT INTO tblUser (id, username, firstName, lastName, role)
     VALUES (:id, :username, :firstName, :lastName, :role)'
);
foreach ([
    [10, 'participant-a', 'Part', 'A', AETHER_ROLE_PARTICIPANT],
    [20, 'participant-b', 'Part', 'B', AETHER_ROLE_PARTICIPANT],
    [30, 'director', 'Dir', 'Ector', AETHER_ROLE_DIRECTOR],
    [40, 'administrator', 'Admin', 'Istrator', AETHER_ROLE_ADMINISTRATOR],
] as [$id, $username, $firstName, $lastName, $role]) {
    $insertUser->execute(compact('id', 'username', 'firstName', 'lastName', 'role'));
}

$pdo->exec("INSERT INTO tblCharacter (id, idUser, type, state, class) VALUES
    (1, 10, 'player', 'draft', 'fighter'),
    (2, 20, 'player', 'draft', 'scholar')");
$pdo->exec("INSERT INTO tblSkill (id, visibility) VALUES (1, 'public'), (2, 'secret')");

aetherStartSession();
$_SESSION = [
    'user' => [
        'id' => 10,
        'username' => 'participant-a',
        'firstName' => 'Part',
        'lastName' => 'A',
        'role' => AETHER_ROLE_ADMINISTRATOR,
    ],
];

$loadedParticipant = aetherLoadAuthenticatedUser($pdo);
assertAuthenticatedUserTest(
    ($loadedParticipant['role'] ?? null) === AETHER_ROLE_PARTICIPANT,
    'Een vervalste administratorrol in de sessie mag de databankrol niet vervangen.'
);

$_SESSION['user']['id'] = 30;
$_SESSION['user']['role'] = AETHER_ROLE_PARTICIPANT;
$loadedDirector = aetherLoadAuthenticatedUser($pdo);
assertAuthenticatedUserTest(
    ($loadedDirector['role'] ?? null) === AETHER_ROLE_DIRECTOR,
    'Een director moet zijn vertrouwde databankrol behouden.'
);

$_SESSION['user']['id'] = 40;
$loadedAdministrator = aetherLoadAuthenticatedUser($pdo);
assertAuthenticatedUserTest(
    ($loadedAdministrator['role'] ?? null) === AETHER_ROLE_ADMINISTRATOR,
    'Een administrator moet zijn vertrouwde databankrol behouden.'
);

$_SESSION['user'] = [
    'id' => 50,
    'username' => 'new-user',
    'firstName' => 'New',
    'lastName' => 'User',
    'role' => AETHER_ROLE_ADMINISTRATOR,
];
$beforeDeniedLoad = (int) $pdo->query('SELECT COUNT(*) FROM tblUser')->fetchColumn();
$missingUser = aetherLoadAuthenticatedUser($pdo);
$afterDeniedLoad = (int) $pdo->query('SELECT COUNT(*) FROM tblUser')->fetchColumn();
assertAuthenticatedUserTest($missingUser === null, 'Een onbekende gebruiker mag op een API-route niet automatisch toegang krijgen.');
assertAuthenticatedUserTest(
    $beforeDeniedLoad === $afterDeniedLoad,
    'Een geweigerde API-authenticatie mag geen gebruiker in de databank aanmaken.'
);

$provisionedUser = aetherLoadAuthenticatedUser($pdo, true);
assertAuthenticatedUserTest(
    ($provisionedUser['role'] ?? null) === AETHER_ROLE_PARTICIPANT,
    'Een login-bootstrap moet een nieuwe gebruiker altijd als participant registreren.'
);
assertAuthenticatedUserTest(
    (string) $pdo->query('SELECT role FROM tblUser WHERE id = 50')->fetchColumn() === AETHER_ROLE_PARTICIPANT,
    'Een meegestuurde sessierol mag de rol bij registratie niet bepalen.'
);

$participant = ['id' => 10, 'role' => AETHER_ROLE_PARTICIPANT];
$ownCharacter = aetherFetchCharacterAccessRecord($pdo, 1);
$otherCharacter = aetherFetchCharacterAccessRecord($pdo, 2);
assertAuthenticatedUserTest($ownCharacter !== null && aetherCanEditCharacter($participant, $ownCharacter), 'Eigen personage moet bewerkbaar zijn.');
assertAuthenticatedUserTest($otherCharacter !== null && !aetherCanEditCharacter($participant, $otherCharacter), 'Een ander personage mag niet bewerkbaar zijn.');

if ($ownCharacter !== null && aetherCanEditCharacter($participant, $ownCharacter)) {
    $pdo->exec("UPDATE tblCharacter SET class = 'guardian' WHERE id = 1");
}
assertAuthenticatedUserTest(
    (string) $pdo->query('SELECT class FROM tblCharacter WHERE id = 1')->fetchColumn() === 'guardian',
    'Een toegestane wijziging aan het eigen personage moet werken.'
);

$beforeDeniedWrite = (string) $pdo->query('SELECT class FROM tblCharacter WHERE id = 2')->fetchColumn();
if ($otherCharacter !== null && aetherCanEditCharacter($participant, $otherCharacter)) {
    $pdo->exec("UPDATE tblCharacter SET class = 'tampered' WHERE id = 2");
}
$afterDeniedWrite = (string) $pdo->query('SELECT class FROM tblCharacter WHERE id = 2')->fetchColumn();
assertAuthenticatedUserTest(
    $beforeDeniedWrite === $afterDeniedWrite,
    'Een geweigerde objectwijziging mag geen gegevens veranderen.'
);

assertAuthenticatedUserTest(aetherCanManageSkill($pdo, $participant, 1), 'Participant moet een publieke vaardigheid kunnen gebruiken.');
assertAuthenticatedUserTest(!aetherCanManageSkill($pdo, $participant, 2), 'Participant mag geen geheime vaardigheid beheren.');
assertAuthenticatedUserTest(aetherCanManageSkill($pdo, $loadedDirector, 2), 'Director moet geheime vaardigheden kunnen beheren.');
assertAuthenticatedUserTest(aetherCanManageSkill($pdo, $loadedAdministrator, 2), 'Administrator moet geheime vaardigheden kunnen beheren.');

$_SESSION = [];
assertAuthenticatedUserTest(
    aetherLoadAuthenticatedUser($pdo) === null,
    'Zonder aangemelde sessie-identiteit moet beschermde toegang worden geweigerd.'
);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Authenticated-user database tests passed.\n";
