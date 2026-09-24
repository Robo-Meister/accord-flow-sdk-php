# Completed Envelope artifact retrieval contract

This contract requires AccordFlow runtime predecessor
`ACCORD-FLOW-SIGNED-DOCUMENT-RETRIEVAL-CONTRACT-001` (runtime PR #1010).

## Artifact boundaries

| Concept | Meaning | SDK operation |
| --- | --- | --- |
| Source document | Immutable input submitted for signing | Existing Envelope document operations |
| Executed document | Provider-produced final signed-document bytes | `listEnvelopeExecutedDocuments()` and `downloadEnvelopeExecutedDocument()` |
| Evidence bundle | Persisted evidence archive/package | `listEnvelopeEvidenceBundles()`, `createEnvelopeEvidenceBundle()`, and `downloadEnvelopeEvidenceBundle()` |
| Records export | Broader records/evidence export | `downloadEnvelopeRecords()` |

The records export is not a substitute for the executed signed document. The
SDK never falls back between these contracts.

## Reconciliation sequence

```text
SIGNATURE.COMPLETED
        ↓
list executed documents
        ↓
select exact artifact belonging to expected source document
        ↓
download exact bytes
        ↓
verify hash / length
        ↓
list/create evidence bundle
        ↓
download exact evidence bytes
```

Descriptors are returned without field remapping so runtime/provider identity
and integrity fields such as `artifactId`, `artifactReference`, `envelopeId`,
`sourceDocumentId`, `sha256`, `sourceSha256`, `mimeType`, `byteLength`, and
`completedAt` remain available. The SDK does not decide which descriptor Robo
Connector should accept. That decision and hash/length verification belong to
Robo Connector reconciliation.

Download operations return `AccordFlowBinaryResponse`. Its `body` is the exact
response byte string; the transport does not trim, decode, transcode, or
normalize it. Evidence creation accepts a runtime payload and optional
`Idempotency-Key`. Repeated calls are sent to the runtime, whose idempotency
contract controls replay; the SDK performs no caching.

The fail-closed runtime response HTTP 409 with code
`EXECUTED_DOCUMENT_BYTES_NOT_PRODUCED_BY_PROVIDER` remains an
`AccordFlowException`. Its HTTP status is available from `getStatusCode()` and
the complete decoded runtime error payload from `getResponseBody()`.
