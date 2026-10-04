<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../auth/accessControl.php';
require_once __DIR__ . '/../shared/validation.php';
require_once __DIR__ . '/../shared/response.php';
require_once __DIR__ . '/userService.php';

try {
    aetherRequirePrivilegedUser($pdo);
    aetherValidateInput($_GET, []);
    aetherJsonResponse(aetherUserList($pdo));
} catch (AetherValidationException $e) {
    aetherJsonValidationError($e->getValidationErrors());
} catch (Throwable $e) {
    error_log('getUserList: ' . $e->getMessage());
    aetherJsonError(500, 'Server error while loading user list.');
}
