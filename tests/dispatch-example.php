<?php

declare(strict_types=1);

// Execute only the documented Envelope example against a recording fake.
// This is an example/ownership regression, not an HTTP integration test.
$readme = file_get_contents(__DIR__ . '/../README.md');
if (!is_string($readme)) {
    throw new RuntimeException('README is unavailable.');
}
$start = strpos($readme, '## Canonical envelope lifecycle');
$end = strpos($readme, '## Completed Envelope retrieval');
if ($start === false || $end === false || $end <= $start) {
    throw new RuntimeException('Canonical lifecycle example is missing.');
}
preg_match_all('/```php\s*\n(.*?)```/s', substr($readme, $start, $end - $start), $blocks);
if (count($blocks[1]) !== 2) {
    throw new RuntimeException('Expected separate preparation and dispatch examples.');
}

final class DispatchExampleClient
{
    public array $calls = [];

    public function __call(string $name, array $arguments): array
    {
        $this->calls[] = [$name, $arguments];
        return match ($name) {
            'createEnvelope' => ['id' => 42],
            'addEnvelopeDocument' => ['id' => 3],
            'addEnvelopeRecipients' => ['recipients' => [['id' => 7, 'email' => 'client@example.com']]],
            'createEnvelopeEmbeddedSession' => ['id' => 9, 'signingUrl' => 'https://sign.example/session/opaque'],
            'sendEnvelope' => ['id' => 42, 'status' => 'SENT'],
            'recordEnvelopeDeliveryProof' => ['id' => 10],
            default => throw new RuntimeException('Unexpected operation: ' . $name),
        };
    }
}

$run = static function (array $receipt) use ($blocks): DispatchExampleClient {
    $client = new DispatchExampleClient();
    $communicationReceipt = $receipt;
    foreach ($blocks[1] as $code) {
        eval($code);
    }
    return $client;
};

$receipts = [
    [],
    ['status' => 'FAILED'],
    ['status' => 'QUEUED'],
    ['status' => 'SENT', 'messageId' => 'message-1', 'sentAt' => '2026-09-21T08:00:00'],
];
foreach ($receipts as $receipt) {
    $client = $run($receipt);
    $names = array_column($client->calls, 0);
    $expected = ['createEnvelope', 'addEnvelopeDocument', 'addEnvelopeRecipients', 'createEnvelopeEmbeddedSession', 'sendEnvelope'];
    $delivered = ($receipt['status'] ?? null) === 'SENT';
    if ($delivered) {
        $expected[] = 'recordEnvelopeDeliveryProof';
    }
    if ($names !== $expected) {
        throw new RuntimeException('Invitation example changed mutation order or invented a delivery proof.');
    }
    $create = $client->calls[0][1][0];
    $send = $client->calls[4][1][1];
    foreach ([$create, $send] as $payload) {
        if (isset($payload['compliance']) || isset($payload['consent']) || isset($payload['intent'])
            || ($payload['notificationOwnership'] ?? null) !== 'EXTERNAL') {
            throw new RuntimeException('Dispatch example must not provide signer evidence or native delivery.');
        }
    }
    if (($create['sendImmediately'] ?? null) !== false) {
        throw new RuntimeException('Preparation must not activate the Envelope.');
    }
    if ($client->calls[3][1][1]['recipientId'] !== 7) {
        throw new RuntimeException('Session must use the returned recipient identity.');
    }
    $keys = array_map(static fn (array $call): mixed => $call[1][array_key_last($call[1])], $client->calls);
    if (count($keys) !== count(array_unique($keys))) {
        throw new RuntimeException('Each mutation must have a distinct idempotency key.');
    }
    if ($delivered) {
        $proof = $client->calls[5][1][1];
        if ($proof['recipientId'] !== 7 || $proof['providerEventId'] !== $receipt['messageId']
            || $proof['occurredAt'] !== $receipt['sentAt']) {
            throw new RuntimeException('Proof must retain actual recipient and Communication receipt coordinates.');
        }
    }
}
echo "Canonical dispatch/consent example OK (4 scenarios)\n";
