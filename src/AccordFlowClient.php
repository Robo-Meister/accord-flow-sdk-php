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

    public function get(string $path): mixed
    {
        return $this->request('GET', $path);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function post(string $path, array $payload): mixed
    {
        return $this->request('POST', $path, $payload);
    }

    /**
     * @param array<string, mixed>|null $jsonPayload
     */
    public function request(string $method, string $path, ?array $jsonPayload = null): mixed
    {
        $headers = $this->formatHeaders([
            ...$this->defaultHeaders,
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
    public function multipart(string $path, string $filePath, array $fields = []): mixed
    {
        if (!is_file($filePath)) {
            throw new AccordFlowException(sprintf('File not found: %s', $filePath));
        }

        $payload = array_filter($fields, static fn (mixed $value): bool => $value !== null);
        $payload['file'] = new CurlFile($filePath);

        return $this->send('POST', $path, $this->formatHeaders($this->defaultHeaders), $payload);
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
