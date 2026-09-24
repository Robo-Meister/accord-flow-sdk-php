<?php

declare(strict_types=1);

use AccordFlow\AccordFlowBinaryResponse;
use AccordFlow\AccordFlowClient;
use AccordFlow\AccordFlowException;

require __DIR__ . '/../src/AccordFlowBinaryResponse.php';
require __DIR__ . '/../src/AccordFlowException.php';
require __DIR__ . '/../src/AccordFlowClient.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . sprintf(
            "\nExpected: %s\nActual: %s",
            var_export($expected, true),
            var_export($actual, true),
        ));
    }
}

$port = random_int(20000, 40000);
$requestLog = tempnam(sys_get_temp_dir(), 'accord-flow-requests-');
$serverLog = tempnam(sys_get_temp_dir(), 'accord-flow-server-');
if ($requestLog === false || $serverLog === false) {
    throw new RuntimeException('Unable to create test logs.');
}

$command = sprintf(
    'ACCORD_FLOW_REQUEST_LOG=%s %s -S 127.0.0.1:%d %s',
    escapeshellarg($requestLog),
    escapeshellarg(PHP_BINARY),
    $port,
    escapeshellarg(__DIR__ . '/fixtures/retrieval-server.php'),
);
$pipes = [];
$process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']], $pipes);
if (!is_resource($process)) {
    throw new RuntimeException('Unable to start retrieval contract server.');
}

try {
    $client = new AccordFlowClient('http://127.0.0.1:' . $port);
    for ($attempt = 0; $attempt < 50; $attempt++) {
        try {
            $client->status();
            break;
        } catch (AccordFlowException) {
            usleep(20_000);
        }
    }

    $documents = $client->listEnvelopeExecutedDocuments(123);
    assertSameValue('artifact-1', $documents['documents'][0]['artifactId'] ?? null, 'Executed descriptors must remain unchanged.');

    $executed = $client->downloadEnvelopeExecutedDocument(123, 'artifact-1');
    assertSameValue(true, $executed instanceof AccordFlowBinaryResponse, 'Executed download must use the binary response contract.');
    assertSameValue("%PDF-1.7\r\n\0\xFF{\"json\":true}\n \t", $executed->body, 'Executed bytes changed.');

    $bundles = $client->listEnvelopeEvidenceBundles(123);
    assertSameValue(456, $bundles[0]['bundleId'] ?? null, 'Evidence bundle discovery failed.');

    $key = 'reconcile-123:bundle';
    $client->createEnvelopeEvidenceBundle(123, ['requestedBy' => 'robo-connector'], $key);
    $client->createEnvelopeEvidenceBundle(123, ['requestedBy' => 'robo-connector'], $key);
    assertSameValue('reconcile-123:bundle', $key, 'Repeated calls mutated the caller idempotency key.');
    $client->createEnvelopeEvidenceBundle(123);

    $evidence = $client->downloadEnvelopeEvidenceBundle(123, 456);
    assertSameValue("PK\x03\x04\0\xFE\r\n[1,2,3]\n \t", $evidence->body, 'Evidence bundle bytes changed.');
    assertSameValue(['kind' => 'records-export'], $client->downloadEnvelopeRecords(123), 'Records export contract changed.');

    try {
        $client->downloadEnvelopeExecutedDocument(123, 'no-provider-bytes');
        throw new RuntimeException('Expected the runtime 409 to throw.');
    } catch (AccordFlowException $error) {
        assertSameValue(409, $error->getStatusCode(), '409 status was not preserved.');
        assertSameValue(
            'EXECUTED_DOCUMENT_BYTES_NOT_PRODUCED_BY_PROVIDER',
            $error->getResponseBody()['code'] ?? null,
            'Runtime error code was not preserved.',
        );
        assertSameValue('Provider completed without final document bytes.', $error->getMessage(), 'Runtime message was not preserved.');
    }

    $requests = array_map(
        static fn (string $line): array => json_decode($line, true, 512, JSON_THROW_ON_ERROR),
        file($requestLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [],
    );
    $contracts = array_map(static fn (array $request): string => $request['method'] . ' ' . $request['path'], $requests);
    foreach ([
        'GET /api/envelopes/123/executed-documents',
        'GET /api/envelopes/123/executed-documents/artifact-1/content',
        'GET /api/envelopes/123/evidence/bundles',
        'POST /api/envelopes/123/evidence/bundle',
        'GET /api/envelopes/123/evidence/bundles/456/content',
        'GET /api/envelopes/123/records',
    ] as $contract) {
        assertSameValue(true, in_array($contract, $contracts, true), 'Missing exact request contract: ' . $contract);
    }

    $creates = array_values(array_filter($requests, static fn (array $request): bool => $request['method'] === 'POST'));
    assertSameValue('reconcile-123:bundle', $creates[0]['headers']['Idempotency-Key'] ?? null, 'Idempotency-Key was not sent.');
    assertSameValue('reconcile-123:bundle', $creates[1]['headers']['Idempotency-Key'] ?? null, 'Repeated Idempotency-Key changed.');
    assertSameValue(false, isset($creates[2]['headers']['Idempotency-Key']), 'Empty idempotency header was sent.');
    assertSameValue('{"requestedBy":"robo-connector"}', $creates[0]['body'], 'Evidence creation payload changed.');
} finally {
    proc_terminate($process);
    proc_close($process);
    @unlink($requestLog);
    @unlink($serverLog);
}

echo "AccordFlow retrieval integration contract OK\n";
