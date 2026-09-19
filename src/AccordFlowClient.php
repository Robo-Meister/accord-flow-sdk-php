<?php

declare(strict_types=1);

namespace AccordFlow;

use CurlFile;

final class AccordFlowClient
{
    private string $baseUrl;

    /** @var array<string, string> */
    private array $defaultHeaders;

    public function __construct(
        string $baseUrl,
        private readonly ?string $bearerToken = null,
        ?string $tenantId = null,
        private readonly int $timeoutSeconds = 30,
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->defaultHeaders = [
            'Accept' => 'application/json',
        ];

        if ($bearerToken !== null && $bearerToken !== '') {
            $this->defaultHeaders['Authorization'] = 'Bearer ' . $bearerToken;
        }

        if ($tenantId !== null && $tenantId !== '') {
            $this->defaultHeaders['X-Tenant-ID'] = $tenantId;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        return $this->get('/api/status');
    }

    public function login(string $username, string $password): mixed
    {
        return $this->post('/api/auth/login', [
            'username' => $username,
            'password' => $password,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function signup(array $payload): mixed
    {
        return $this->post('/api/auth/signup', $payload);
    }

    public function usage(string $username): mixed
    {
        return $this->get('/api/auth/usage/' . rawurlencode($username));
    }


    /**
     * Canonical application-level signing lifecycle. Low-level sign()/verify()
     * remain available for cryptographic/provider operations.
     *
     * @param array<string, mixed> $payload
     */
    public function createEnvelope(array $payload, ?string $idempotencyKey = null): mixed
    {
        return $this->post('/api/envelopes', $payload, $this->idempotencyHeaders($idempotencyKey));
    }

    public function getEnvelope(int|string $envelopeId): mixed
    {
        return $this->get('/api/envelopes/' . rawurlencode((string) $envelopeId));
    }

    public function getEnvelopeStatus(int|string $envelopeId): mixed
    {
        return $this->get('/api/envelopes/' . rawurlencode((string) $envelopeId) . '/status');
    }

    public function addEnvelopeDocument(
        int|string $envelopeId,
        string $filePath,
        ?string $idempotencyKey = null,
    ): mixed {
        return $this->multipart(
            '/api/envelopes/' . rawurlencode((string) $envelopeId) . '/documents',
            $filePath,
            [],
            'files',
            $this->idempotencyHeaders($idempotencyKey),
        );
    }

    /**
     * @param list<array<string, mixed>> $recipients
     */
    public function addEnvelopeRecipients(
        int|string $envelopeId,
        array $recipients,
        ?string $idempotencyKey = null,
    ): mixed {
        return $this->post(
            '/api/envelopes/' . rawurlencode((string) $envelopeId) . '/recipients',
            $recipients,
            $this->idempotencyHeaders($idempotencyKey),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function sendEnvelope(
        int|string $envelopeId,
        array $payload,
        ?string $idempotencyKey = null,
    ): mixed {
        return $this->post(
            '/api/envelopes/' . rawurlencode((string) $envelopeId) . '/send',
            $payload,
            $this->idempotencyHeaders($idempotencyKey),
        );
    }

    public function getEnvelopeAudit(int|string $envelopeId): mixed
    {
        return $this->get('/api/envelopes/' . rawurlencode((string) $envelopeId) . '/audit');
    }

    public function getEnvelopeEvidence(int|string $envelopeId): mixed
    {
        return $this->get('/api/envelopes/' . rawurlencode((string) $envelopeId) . '/evidence');
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createEnvelopeEvidenceBundle(int|string $envelopeId, array $payload = []): mixed
    {
        return $this->post(
            '/api/envelopes/' . rawurlencode((string) $envelopeId) . '/evidence/bundle',
            $payload,
        );
    }

    public function downloadEnvelopeRecords(int|string $envelopeId): mixed
    {
        return $this->get('/api/envelopes/' . rawurlencode((string) $envelopeId) . '/records');
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function sign(array $payload): mixed
    {
        return $this->post('/api/signature/sign', $payload);
    }

    /**
     * @param array<string, string|int|float|bool|null> $fields
     */
    public function signFile(string $filePath, int $envelopeId, array $fields = []): mixed
    {
        return $this->multipart('/api/signature/sign', $filePath, [
            ...$fields,
            'envelopeId' => $envelopeId,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function verify(array $payload): mixed
    {
        return $this->post('/api/signature/verify', $payload);
    }

    /**
     * @param array<string, string|int|float|bool|null> $fields
     */
    public function verifyFile(string $filePath, string $signature, array $fields = []): mixed
    {
        return $this->multipart('/api/signature/verify', $filePath, [
            'signature' => $signature,
            ...$fields,
        ]);
    }

    /** @param array<string, string> $headers */
    public function get(string $path, array $headers = []): mixed
    {
        return $this->request('GET', $path, null, $headers);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function post(string $path, array $payload, array $headers = []): mixed
    {
        return $this->request('POST', $path, $payload, $headers);
    }

    /**
     * @param array<string, mixed>|null $jsonPayload
     * @param array<string, string> $headers
     */
    public function request(string $method, string $path, ?array $jsonPayload = null, array $headers = []): mixed
    {
        $headers = $this->formatHeaders([
            ...$this->defaultHeaders,
            ...$headers,
            'Content-Type' => 'application/json',
        ]);

        $body = null;
        if ($jsonPayload !== null) {
            $body = json_encode($jsonPayload, JSON_THROW_ON_ERROR);
        }

        return $this->send($method, $path, $headers, $body);
    }

    /**
     * @param array<string, string|int|float|bool|null> $fields
     */
    public function multipart(
        string $path,
        string $filePath,
        array $fields = [],
        string $fileField = 'file',
        array $headers = [],
    ): mixed {
        if (!is_file($filePath)) {
            throw new AccordFlowException(sprintf('File not found: %s', $filePath));
        }

        $payload = array_filter($fields, static fn (mixed $value): bool => $value !== null);
        $payload[$fileField] = new CurlFile($filePath);

        return $this->send(
            'POST',
            $path,
            $this->formatHeaders([...$this->defaultHeaders, ...$headers]),
            $payload,
        );
    }

    /**
     * @param list<string> $headers
     * @param array<string, scalar|CurlFile>|string|null $body
     */
    private function send(string $method, string $path, array $headers, array|string|null $body = null): mixed
    {
        $curl = curl_init($this->baseUrl . '/' . ltrim($path, '/'));
        if ($curl === false) {
            throw new AccordFlowException('Unable to initialize cURL.');
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
        ]);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new AccordFlowException('HTTP request failed: ' . $error);
        }

        curl_close($curl);
        $decoded = $this->decodeResponse($response);

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($decoded) && isset($decoded['message'])
                ? (string) $decoded['message']
                : sprintf('AccordFlow API returned HTTP %d.', $statusCode);

            throw new AccordFlowException($message, $statusCode, $decoded);
        }

        return $decoded;
    }

    private function decodeResponse(string $response): mixed
    {
        $trimmed = trim($response);
        if ($trimmed === '') {
            return null;
        }

        $firstCharacter = $trimmed[0];
        if ($firstCharacter !== '{' && $firstCharacter !== '[' && $trimmed !== 'true' && $trimmed !== 'false' && $trimmed !== 'null') {
            return $response;
        }

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, string> */
    private function idempotencyHeaders(?string $idempotencyKey): array
    {
        $idempotencyKey = $idempotencyKey !== null ? trim($idempotencyKey) : '';

        return $idempotencyKey === '' ? [] : ['Idempotency-Key' => $idempotencyKey];
    }

    /**
     * @param array<string, string> $headers
     * @return list<string>
     */
    private function formatHeaders(array $headers): array
    {
        return array_map(
            static fn (string $name, string $value): string => $name . ': ' . $value,
            array_keys($headers),
            array_values($headers),
        );
    }
}
