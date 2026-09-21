# AccordFlow PHP SDK

The AccordFlow PHP SDK is a small cURL-based client for connecting PHP 8.1+
applications to the AccordFlow Signature API. It supports JSON requests,
multipart file uploads, bearer-token authentication, and the tenant header used
by local open API mode.

## Installation

For application deployments, resolve the published release through Composer:

```bash
composer require robo-meister/accord-flow-api:^0.0.2
```

Verify that your Composer registry resolves the release before updating an
application lockfile. A Git tag and registry availability are separate checks.
Local path repositories are for SDK development, not release substitution.
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

This example requires the AccordFlow runtime fix
`ACCORD-FLOW-DISPATCH-VS-SIGNER-CONSENT-BOUNDARY-001`, not merely SDK v0.0.2.
Preparing or dispatching an Envelope does not assert signer consent or intent.
The actual signer supplies those facts through the signing interaction.

Prepare the approved immutable document, recipient and signing session. This
stage neither activates the Envelope nor sends an invitation.

```php
$envelope = $client->createEnvelope([
    'name' => 'Engagement agreement',
    'organisationId' => 'org-123',
    'globalDocumentId' => 'doc-123',
    'contextType' => 'legal_matter',
    'contextId' => 'matter-123',
    'sendImmediately' => false,
    'notificationOwnership' => 'EXTERNAL',
    'rcContext' => ['correlationId' => 'signing-123'],
], 'signing-123:create');

$envelopeId = $envelope['id'];
$client->addEnvelopeDocument($envelopeId, '/path/to/approved.pdf', 'signing-123:document');

$recipientResult = $client->addEnvelopeRecipients($envelopeId, [[
    'name' => 'Client',
    'email' => 'client@example.com',
    'routingOrder' => 1,
    'role' => 'SIGNER',
]], 'signing-123:recipients');
$recipientId = $recipientResult['recipients'][0]['id'];

$session = $client->createEnvelopeEmbeddedSession($envelopeId, [
    'recipientId' => $recipientId,
    'locale' => 'en-US',
], 'signing-123:session');
$signingUrl = $session['signingUrl'] ?? null;
if (!is_string($signingUrl) || filter_var($signingUrl, FILTER_VALIDATE_URL) === false) {
    throw new RuntimeException('AccordFlow did not return a signing destination.');
}
```

The application now creates and reviews its Communication draft. Before the
following step it must revalidate the exact approved revision, signer contact,
session identity, session expiry and professional send authorization. Never
silently replace an expired session URL in an already approved message.

```php
// Run only after the application's professional review and current-state checks.
$client->sendEnvelope($envelopeId, [
    'initiatedBy' => 'professional@example.com',
    'notificationOwnership' => 'EXTERNAL',
], 'signing-123:send');

// Communication, not AccordFlow, now delivers the approved invitation.
// $communicationReceipt comes from that subsystem's durable send result.
if (($communicationReceipt['status'] ?? null) === 'SENT') {
    $client->recordEnvelopeDeliveryProof($envelopeId, [
        'recipientId' => $recipientId,
        'channel' => 'EMAIL',
        'eventType' => 'DISPATCHED',
        'status' => 'SENT',
        'destination' => 'client@example.com',
        'templateId' => 'legal.signature.invitation.v1',
        'providerEventId' => $communicationReceipt['messageId'],
        'occurredAt' => $communicationReceipt['sentAt'],
    ], 'signing-123:delivery-proof');
}
```

A failed delivery-proof request must retry only the proof, not send the email
again. Likewise, an activated Envelope is not proof that Communication delivered
anything. `SENT` is not signed-document/evidence reconciliation completion.

### Dispatch policy, not signer evidence

The corrected send API accepts optional `dispatchPolicy` containing only
`policyReferences` and `retention`. Omitted retention uses the existing
`signature.retention.envelope` configuration and is still validated/persisted by
AccordFlow. Explicit directives still have to satisfy the configured bounds.
These defaults are application configuration, not a jurisdiction-specific legal
compliance guarantee.

Legacy `compliance` remains a deprecated wire adapter: only its Envelope policy
and retention fields are used at send. Its `consent` and `intent` fields do not
produce signer evidence. Do not send both `dispatchPolicy` and `compliance`.
Draft creation ignores legacy signer data; pass policy overrides on the later
send request. `sendImmediately=true` uses the same corrected dispatch boundary.
Clients that relied on send creating signer evidence must migrate to the real
signing interaction; there is no compatibility switch that fabricates consent.

### Application-owned invitation delivery

`EXTERNAL` ownership suppresses native dispatch, reminder and void notifications;
AccordFlow still owns recipient authentication, signing, lifecycle and evidence.
The provider-owned `signingUrl` requires runtime public-signing configuration.
Do not reconstruct the frontend route or log the URL/capability token.

SDK v0.0.2 already includes `addEnvelopeRecipients()`,
`createEnvelopeEmbeddedSession()`, `getEnvelopeEmbeddedSessions()`,
`recordEnvelopeDeliveryProof()` and `sendEnvelope()`. This documentation correction
does not change those PHP methods or move the existing v0.0.2 tag.

The canonical application API is `/api/envelopes/*`. Idempotency headers are not
by themselves proof of server-side replay handling; a mutating transport timeout
remains an unknown outcome until reconciled. Low-level crypto helpers are not
substitutes for the Legal Office Envelope/session flow.

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
        // This value must reflect the actual signer's explicit choice, not a default.
        'accepted' => true,
        'method' => 'CHECKBOX',
    ],
]);

$signature = $response['signature'] ?? null;
$redirectUrl = $response['redirectUrl'] ?? null;
```

The signing endpoint requires an existing envelope and actual signer consent.
Sender approval of an invitation is not that consent. Never invent signer IP,
authentication, consent timestamps or intent to satisfy request validation.
Some providers return `redirectUrl` for a browser handoff; preserve their returned
`verificationReference` for subsequent verification.

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

// The second signFile argument is the multipart envelopeId form field.
```

These are legacy low-level interfaces, not the Legal Office invitation path.
Their identity/consent handling requires separate verification before use in a
signer-facing workflow; this dispatch-boundary change does not certify them.

## Generic requests

Use generic helpers only for operations without an available typed wrapper:

```php
$usage = $client->get('/api/auth/usage/demo');
$createdUser = $client->post('/api/auth/signup', [
    'username' => 'demo',
    'password' => 'change-me',
]);
```

## Error handling

Non-2xx responses and transport failures throw `AccordFlowException`. The
exception includes the HTTP status code and decoded JSON response when available.
Do not log provider payloads or exception bodies containing signing capabilities.

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
    error_log('AccordFlow verification failed; HTTP ' . $error->getStatusCode());
}
```
