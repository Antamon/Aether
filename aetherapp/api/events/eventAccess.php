<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/accessControl.php';

final class AetherEventException extends RuntimeException
{
    public function __construct(private int $httpStatus, string $message)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

function aetherCanManageEvents(array $currentUser): bool
{
    return aetherIsPrivilegedRole((string) ($currentUser['role'] ?? ''));
}

function aetherRequireEventManager(array $currentUser): void
{
    if (!aetherCanManageEvents($currentUser)) {
        throw new AetherEventException(403, 'Je hebt geen rechten om events te beheren.');
    }
}

function aetherCanReadEventParticipationForUser(array $currentUser, int $userId): bool
{
    return $userId === (int) ($currentUser['id'] ?? 0) || aetherCanManageEvents($currentUser);
}

