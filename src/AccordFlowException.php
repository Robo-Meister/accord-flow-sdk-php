<?php

declare(strict_types=1);

namespace AccordFlow;

use RuntimeException;

final class AccordFlowException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $statusCode = null,
        private readonly mixed $responseBody = null,
    ) {
        parent::__construct($message, $statusCode ?? 0);
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): mixed
    {
        return $this->responseBody;
    }
}
