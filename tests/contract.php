<?php

declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../src/AccordFlowClient.php');
if (!is_string($source)) {
    throw new RuntimeException('Unable to read AccordFlowClient.php');
}

$required = [
    'function createEnvelope(' => "/api/envelopes",
    'function getEnvelope(' => "/api/envelopes/",
    'function getEnvelopeStatus(' => "/status",
    'function addEnvelopeDocument(' => "/documents",
    'function addEnvelopeRecipients(' => "/recipients",
    'function sendEnvelope(' => "/send",
    'function getEnvelopeAudit(' => "/audit",
    'function getEnvelopeEvidence(' => "/evidence",
    "'Idempotency-Key'" => 'Idempotency-Key',
];

foreach ($required as $needle => $label) {
    if (!str_contains($source, $needle)) {
        throw new RuntimeException(sprintf('Missing SDK contract: %s', $label));
    }
}

echo "AccordFlow envelope SDK contract OK\n";
