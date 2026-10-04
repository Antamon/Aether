<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../db.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/../shared/request.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../shared/idempotency.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/characterAccess.php';
require_once __DIR__ . '/characterSchemas.php';
require_once __DIR__ . '/characterActionService.php';

$currentUser = aetherRequireAuthenticatedUser($pdo);
aetherRequireCsrfToken();

try {
    $requestData = aetherReadJsonObject();
    $input = aetherValidateInput(
        $requestData,
        aetherCharacterRequestSchema('useCharacterSkillAction', $requestData)
    );
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
}

try {
    aetherRequireCharacterActionAccess(
        $pdo,
        $currentUser,
        $input['idCharacter'],
        'Geen rechten om deze actie uit te voeren.'
    );
    $requestKey = aetherRequireIdempotencyKey();
    $result = aetherRunIdempotentMutation(
        $pdo,
        $currentUser,
        'character.action.use_skill',
        $requestKey,
        $input,
        static function () use ($pdo, $currentUser, $input): array {
            $character = aetherRequireCharacterActionAccess(
                $pdo,
                $currentUser,
                (int) $input['idCharacter'],
                'Geen rechten om deze actie uit te voeren.'
            );
            return aetherUseCharacterSkillAction(
                $pdo,
                $currentUser,
                $character,
                $input['idEvent'],
                $input['idSkill'],
                $input['actionCode'],
                $input['actionSubtype'],
                $input['clearBurn']
            );
        },
        static function () use ($pdo, $currentUser, $input): void {
            aetherAuthorizeCharacterSkillActionReplay($pdo, $currentUser, $input);
        }
    );
    aetherJsonResponse($result);
} catch (AetherCharacterActionException $e) {
    if ($e->includesNullDetails()) {
        aetherJsonResponse(['error' => $e->getMessage(), 'details' => null], $e->getHttpStatus());
    }
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (AetherIdempotencyException $e) {
    aetherJsonError($e->getHttpStatus(), $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('useCharacterSkillAction.php failed: ' . $e->getMessage());
    aetherJsonError(500, 'Kon deze vaardigheidsactie niet registreren.');
}
