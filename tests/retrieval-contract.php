<?php

declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../src/AccordFlowClient.php');
$binary = file_get_contents(__DIR__ . '/../src/AccordFlowBinaryResponse.php');
if (!is_string($source) || !is_string($binary)) {
    throw new RuntimeException('Unable to read retrieval SDK sources.');
}

$required = [
    'function listEnvelopeExecutedDocuments(' => '/executed-documents',
    'function downloadEnvelopeExecutedDocument(' => '/executed-documents/',
    'function listEnvelopeEvidenceBundles(' => '/evidence/bundles',
    'function createEnvelopeEvidenceBundle(' => '/evidence/bundle',
    'function downloadEnvelopeEvidenceBundle(' => '/evidence/bundles/',
    'function binaryRequest(' => 'binary request',
    'function sendBinary(' => 'exact binary transport',
    "'Accept' => 'application/octet-stream'" => 'binary accept header',
];

foreach ($required as $needle => $label) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException(sprintf('Missing retrieval SDK contract: %s', $label));
    }
}

foreach (['public readonly string $body', 'contentType()', 'contentLength()', 'filename()'] as $needle) {
    if (!str_contains($binary, $needle)) {
        throw new RuntimeException('Missing exact binary response contract: ' . $needle);
    }
}

$recordsStart = strpos($source, 'function downloadEnvelopeRecords(');
$recordsEnd = strpos($source, 'function sign(', $recordsStart ?: 0);
if ($recordsStart === false || $recordsEnd === false) {
    throw new RuntimeException('Unable to inspect records export method.');
}
$records = substr($source, $recordsStart, $recordsEnd - $recordsStart);
if (!str_contains($records, '/records')
    || str_contains($records, '/executed-documents')
    || str_contains($records, '/evidence/bundles')) {
    throw new RuntimeException('Records export was conflated with executed documents/evidence bundles.');
}

$createBundleStart = strpos($source, 'function createEnvelopeEvidenceBundle(');
$downloadBundleStart = strpos($source, 'function downloadEnvelopeEvidenceBundle(', $createBundleStart ?: 0);
if ($createBundleStart === false || $downloadBundleStart === false) {
    throw new RuntimeException('Unable to inspect evidence bundle mutation.');
}
$bundleCreate = substr($source, $createBundleStart, $downloadBundleStart - $createBundleStart);
if (!str_contains($bundleCreate, 'idempotencyHeaders($idempotencyKey)')) {
    throw new RuntimeException('Evidence bundle creation must propagate Idempotency-Key.');
}

echo "AccordFlow executed-document/evidence-bundle SDK contract OK\n";
