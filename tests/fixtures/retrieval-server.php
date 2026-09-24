<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$headers = function_exists('getallheaders') ? getallheaders() : [];

file_put_contents(
    getenv('ACCORD_FLOW_REQUEST_LOG'),
    json_encode([
        'method' => $method,
        'path' => $path,
        'headers' => $headers,
        'body' => file_get_contents('php://input'),
    ], JSON_THROW_ON_ERROR) . "\n",
    FILE_APPEND,
);

header('Content-Type: application/json');

if ($path === '/api/status') {
    echo '{"status":"ok"}';
    return;
}

if ($path === '/api/envelopes/123/executed-documents') {
    echo json_encode(['documents' => [[
        'artifactId' => 'artifact-1',
        'artifactReference' => 'provider:artifact-1',
        'envelopeId' => 123,
        'sourceDocumentId' => 'source-9',
        'sha256' => 'executed-hash',
        'sourceSha256' => 'source-hash',
        'mimeType' => 'application/pdf',
        'byteLength' => 18,
        'completedAt' => '2026-09-24T12:00:00Z',
    ]]], JSON_THROW_ON_ERROR);
    return;
}

if ($path === '/api/envelopes/123/executed-documents/artifact-1/content') {
    header('Content-Type: application/pdf');
    echo "%PDF-1.7\r\n\0\xFF{\"json\":true}\n \t";
    return;
}

if ($path === '/api/envelopes/123/executed-documents/no-provider-bytes/content') {
    http_response_code(409);
    echo json_encode([
        'error' => 'Executed document unavailable',
        'message' => 'Provider completed without final document bytes.',
        'code' => 'EXECUTED_DOCUMENT_BYTES_NOT_PRODUCED_BY_PROVIDER',
    ], JSON_THROW_ON_ERROR);
    return;
}

if ($path === '/api/envelopes/123/evidence/bundles' && $method === 'GET') {
    echo '[{"bundleId":456}]';
    return;
}

if ($path === '/api/envelopes/123/evidence/bundle' && $method === 'POST') {
    http_response_code(201);
    echo '{"bundleId":456}';
    return;
}

if ($path === '/api/envelopes/123/evidence/bundles/456/content') {
    header('Content-Type: application/zip');
    echo "PK\x03\x04\0\xFE\r\n[1,2,3]\n \t";
    return;
}

if ($path === '/api/envelopes/123/records') {
    echo '{"kind":"records-export"}';
    return;
}

http_response_code(404);
echo '{"message":"not found"}';
