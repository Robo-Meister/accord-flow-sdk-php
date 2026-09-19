# AccordFlow PHP SDK

The AccordFlow PHP SDK is a small cURL-based client for connecting PHP 8.1+
applications to the AccordFlow Signature API. It supports JSON requests,
multipart file uploads, bearer-token authentication, and the tenant header used
by local open API mode.

## Installation

Add the SDK to your application with Composer. For local development, reference
this repository path:

```bash
composer config repositories.accordflow path ../signature/sdk/php
composer require robo-meister/accord-flow-api:*
```

The package requires the PHP `curl` and `json` extensions.

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use AccordFlow\AccordFlowClient;

$client = new AccordFlowClient(
    baseUrl: 'http://localhost:8080',
    bearerToken: getenv('ACCORDFLOW_TOKEN') ?: null,
    tenantId: getenv('ACCORDFLOW_TENANT') ?: null,
);

$status = $client->status();
```

## Canonical envelope lifecycle

Application integrations such as Robo Connector Legal Office should use the Envelope API for signing workflows. The Envelope API separates preparation from dispatch and preserves lifecycle identity for later status, audit, and evidence reconciliation.

```php
$envelope = $client->createEnvelope([
    'name' => 'Engagement agreement',
    'organisationId' => 'org-123',
    'globalDocumentId' => 'doc-123',
    'contextType' => 'legal_matter',
    'contextId' => 'matter-123',
    'rcContext' => [
        'correlationId' => 'signing-123',
    ],
    'compliance' => [
        // Application-approved compliance input.
    ],
], 'signing-123:create');

$envelopeId = $envelope['id'];

$client->addEnvelopeDocument($envelopeId, '/path/to/approved.pdf', 'signing-123:document');

$client->addEnvelopeRecipients($envelopeId, [[
    'name' => 'Client',
    'email' => 'client@example.com',
    'routingOrder' => 1,
    'role' => 'SIGNER',
]], 'signing-123:recipients');

// Applications may perform their own review/communication flow here.

$client->sendEnvelope($envelopeId, [
    'initiatedBy' => 'user-123',
    'compliance' => [
        // Same approved send/compliance context required by the runtime.
    ],
], 'signing-123:send');

$status = $client->getEnvelopeStatus($envelopeId);
$audit = $client->getEnvelopeAudit($envelopeId);
$evidence = $client->getEnvelopeEvidence($envelopeId);
```

The canonical application lifecycle is `/api/envelopes/*`. The lower-level `sign()`, `signFile()`, `verify()`, and `verifyFile()` helpers remain available for cryptographic/provider operations, but they are not the preferred orchestration API for Robo Connector Legal Office.

Mutating envelope helpers accept an optional idempotency key and send it as `Idempotency-Key`. A transport timeout after a mutation must be treated by the caller as an unknown outcome unless it can reconcile the operation through envelope identity/status.

## Sign JSON payloads

```php
$response = $client->sign([
    'envelopeId' => 456,
    'data' => 'Hello world',
    'documentType' => 'txt',
    'profile' => [
        'countryCode' => 'PL',
        'type' => 'EXTERNAL',
        'mode' => 'RAW',
    ],
    'username' => 'demo',
    'consent' => [
        'accepted' => true,
        'method' => 'CHECKBOX',
    ],
]);

$signature = $response['signature'] ?? null;
$redirectUrl = $response['redirectUrl'] ?? null;
```

The signing endpoint requires an existing envelope. Create or look up the
`envelopeId` before calling `sign`, and include an accepted `consent` payload so
the API can capture signer consent evidence. Some providers return `redirectUrl`
for browser-based handoff flows. Redirect the signer to that URL and store any
returned `verificationReference` for later verification lookups.

## Sign and verify files

```php
$signed = $client->signFile('/path/to/document.pdf', 456, [
    'profileId' => 123,
]);

$verification = $client->verifyFile(
    '/path/to/document.pdf',
    $signed['signature'],
    ['profileId' => 123],
);

// File signing also requires an existing envelope ID; the helper sends the
// second argument as the multipart `envelopeId` form field.
```

## Generic requests

Use the generic helpers when the SDK has not added a typed wrapper yet:

```php
$usage = $client->get('/api/auth/usage/demo');
$createdUser = $client->post('/api/auth/signup', [
    'username' => 'demo',
    'password' => 'change-me',
]);
```

## Error handling

Non-2xx responses and transport failures throw `AccordFlowException`. The
exception includes the HTTP status code and decoded response body when the API
returns JSON.

```php
use AccordFlow\AccordFlowException;

try {
    $client->verify([
        'data' => 'Hello world',
        'signature' => $signature,
        'documentType' => 'txt',
        'username' => 'demo',
    ]);
} catch (AccordFlowException $error) {
    error_log($error->getMessage());
    error_log((string) $error->getStatusCode());
}
```
