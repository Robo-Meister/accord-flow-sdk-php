<?php

declare(strict_types=1);

namespace AccordFlow;

/**
 * Exact binary response returned by artifact download operations.
 *
 * Body bytes are not decoded or normalized.
 */
final class AccordFlowBinaryResponse
{
    /**
     * @param array<string, string> $headers Lower-case response header names.
     */
    public function __construct(
        public readonly string $body,
        public readonly int $statusCode,
        public readonly array $headers = [],
    ) {
    }

    public function getHeader(string $name): ?string
    {
        $name = strtolower(trim($name));

        return $name === '' ? null : ($this->headers[$name] ?? null);
    }

    public function contentType(): ?string
    {
        return $this->getHeader('content-type');
    }

    public function contentLength(): ?int
    {
        $value = $this->getHeader('content-length');
        if ($value === null || !ctype_digit(trim($value))) {
            return null;
        }

        return (int) trim($value);
    }

    public function filename(): ?string
    {
        $disposition = $this->getHeader('content-disposition');
        if ($disposition === null) {
            return null;
        }

        if (preg_match('/filename="?([^";]+)"?/i', $disposition, $matches) !== 1) {
            return null;
        }

        return trim($matches[1]);
    }
}
