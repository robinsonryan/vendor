# Implementation Queue

> Deferred work, captured mid-session, picked up deliberately. Managed by `/queue`;
> convention: `$CLAUDE_HARNESS_DIR/notes/implementation-queue.md`. Hand-editing is fine.

## Queued

### Support Pest 5 / PHPUnit 13 / PHP 8.4+ in the constraint matrix
- **Added**: 2026-08-07 · harness health & efficiency session — apps are queued to upgrade to Pest 5 for Tia; consuming apps can't move until this package allows it
- **Tier**: SOLO
- **Why deferred**: harness-wide decision made first; per-package constraint widening is independent work
- **Context**: current: php ^8.3, pest ^3.0|^4.0. Widen composer constraints to include pest ^5 / phpunit ^13 / php 8.4+ and run the suite on the new matrix. Research + decisions: $CLAUDE_HARNESS_DIR/notes/harness-health-research-2026-08.md

### Three domain assertions fail now that the suite actually runs
- **Added**: 2026-09-13 · Pact build (feat/pact) — the suite had never executed here, so these were invisible
- **Tier**: LIGHT
- **Why deferred**: they are vendor/taxon domain behaviour, not the Pact shape work that surfaced them
- **Context**: `tests/Feature/VendorTest.php` — (1) and (2) "has default type of supplier" / "has default status of active" fail because `VendorFactory` never applies the tag attributes; `VendorData`'s defaults only apply through `CreateVendor`. (3) "can be created as a service provider" fails because taxon stores `service_provider` as `service-provider` (`Str::slug` eats the underscore) — the same underscore bug taxon carries on `fix/underscore-tag-values-in-query-scopes`. Decide per case whether the factory or the assertion is wrong, and whether this package should wait on the taxon fix.

## Blocked

## Archive
