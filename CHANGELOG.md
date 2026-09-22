# Changelog

## Unreleased

### Executed-document retrieval
- Add typed `getEnvelopeExecutedDocuments()` descriptor discovery.
- Add exact-byte `downloadEnvelopeExecutedDocument()` via `AccordFlowBinaryResponse`.
- Add typed evidence-bundle discovery/download operations.
- Make `createEnvelopeEvidenceBundle()` carry an optional deterministic `Idempotency-Key`.
- Keep `downloadEnvelopeRecords()` semantically separate from executed-document and evidence-bundle retrieval.
- Target the next immutable patch release after the matching runtime contract is merged; do not move `v0.0.3`.


### Documentation and verification
- Correct the Envelope example: prepare and dispatch without fabricated signer consent or intent.
- Require the matching `ACCORD-FLOW-DISPATCH-VS-SIGNER-CONSENT-BOUNDARY-001` runtime, not merely the PHP SDK version.
- Explain optional Envelope-only `dispatchPolicy`, configured retention defaults and the deprecated policy-only `compliance` adapter.
- Require `EXTERNAL` delivery ownership in the application-owned example and record delivery proof only from an actual Communication send receipt.
- Execute the documented example against a recording fake for both missing and successful delivery receipts.
- No PHP client signatures or method implementations change. Preserve the existing v0.0.2 Git tag; do not move or recreate it for this correction.

## v0.0.2

Integration contract for application-owned signing invitations.

### Added
- Typed embedded signing session creation: `createEnvelopeEmbeddedSession()`.
- Typed embedded session lookup: `getEnvelopeEmbeddedSessions()`.
- Typed external delivery evidence ingestion: `recordEnvelopeDeliveryProof()`.
- Idempotency-Key support for the new mutating operations.
- Explicit documentation for application-owned invitation delivery.

### Changed
- `addEnvelopeRecipients()` normalizes the runtime's top-level recipient list into an explicit `recipients` result.
- Applications should consume the provider-owned `signingUrl` returned by AccordFlow rather than reconstructing a frontend route from the session token.

### Runtime requirement
SDK transport support does not establish server-side lifecycle readiness. External ownership, signing destinations, mutation replay handling and the dispatch/signer-consent boundary must also be present and verified in the deployed AccordFlow runtime.
