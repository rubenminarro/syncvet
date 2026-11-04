<?php

namespace App\Exceptions;

use Exception;

class AppException extends Exception
{
    public function __construct(
        string $message,
        protected int $status = 422,
        protected ?string $errorCode = null,
        protected ?array $errors = null
    ) {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }
}