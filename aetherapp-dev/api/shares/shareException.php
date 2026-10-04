<?php
declare(strict_types=1);

final class AetherShareException extends RuntimeException
{
    public function __construct(private int $httpStatus, string $message, private ?string $errorCode = null)
    {
        parent::__construct($message);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode ?? match ($this->httpStatus) {
            400, 422 => 'shares.invalid_request',
            403 => 'shares.forbidden',
            404 => 'shares.not_found',
            409 => 'shares.conflict',
            503 => 'shares.unavailable',
            default => 'shares.error',
        };
    }
}
