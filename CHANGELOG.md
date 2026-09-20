# Changelog

## v0.0.2 — pending

Prepared integration contract for application-owned signing invitations.

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
The v0.0.2 tag should be published only after the matching AccordFlow runtime support for external notification ownership, signing destinations and idempotent invitation mutations is merged.
