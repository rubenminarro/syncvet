<?php

namespace App\Exceptions;

use Exception;

class AppException extends Exception
{
    /**
     * @param array<string, string> $headers Encabezados de la respuesta (por ejemplo, Retry-After en un 429).
     */
    public function __construct(
        string $message,
        protected int $status = 422,
        protected ?string $errorCode = null,
        protected ?array $errors = null,
        protected array $headers = []
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

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}
