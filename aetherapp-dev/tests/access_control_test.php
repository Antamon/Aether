<?php
declare(strict_types=1);

require_once __DIR__ . '/../api/auth/accessControl.php';

$failures = [];

function assertAccess(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
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

assertAccess(aetherCsrfTokenMatches('same-token', 'same-token'), 'Geldig CSRF-token moet worden geaccepteerd.');
assertAccess(!aetherCsrfTokenMatches('forged-token', 'same-token'), 'Vervalst CSRF-token moet worden geweigerd.');
assertAccess(!aetherCsrfTokenMatches(null, 'same-token'), 'Ontbrekend CSRF-token moet worden geweigerd.');

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Access-control decision tests passed.\n";

