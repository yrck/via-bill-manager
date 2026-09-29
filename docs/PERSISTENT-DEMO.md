# Persistent demo increment

Implemented September 29, 2026. Local fictional data only.

The dashboard now reads regular relational tables: portfolios → locations → utility accounts → expected bills → statements. Missing obligations have no statement or assumed amount. The Portfolio page browses four locations and 24 accounts. Seeding is explicit, restricted to opted-in local/testing environments, repeatable, and preserves existing edits. No records are created by page visits.

September 2026 and the September 29 snapshot date remain fixed for predictable demonstrations. Expected-by dates include the assumed grace period; an unreceived bill is missing only after that date. Future expectations stay in the coverage denominator but do not enter the missing queue. Due-date alerts include verified statements and make no payment-status claim. Amounts are USD integer cents; the demo does not support mixed-currency totals.

This is a prototype schema: one account-period obligation and one accepted statement per obligation. Statement versions, multi-meter and consolidated billing, source files, actual validation execution, recurring schedule generation, account lifecycle, addresses, authorization, and operational history are not implemented. Seeded findings are fictional examples. The fixed demo portfolio query is not a tenant authorization boundary.

Next: refine the review workflow with sample bills and locations, including source-document preview and extraction requirements. Authentication and organization/property authorization must precede real-data loading. Preserve ACCOUNT-REVIEW-CHECKLIST.md as the unresolved requirements record.

Validation includes database-driven totals, missing vs future expectations, due dates, seeder idempotence, demo gating and evidence scoping. The base test case explicitly forces SQLite in memory before RefreshDatabase can run; Docker-exported application settings previously overrode PHPUnit environment defaults during initial validation. That initial run refreshed only this project's fictional local database; no real data was present. The local demo is reseeded afterward.

## Bill review increment

Implemented: `/bills` lists the 24 expected obligations with property/status filters and case-insensitive account/supplier/property search. `/bills/{expectedBill}` shows the statement or missing obligation, original finding, source-document gap, and demo decision history.

Received statements can move from review to verified and back with a required 10–2,000 character note. The original evidence and amounts are unchanged. Each transition and note is saved atomically with a version increment; stale submissions are rejected, even if another session verifies and reopens the same statement. Database row locking serializes PostgreSQL decisions. The statement ID and loaded version are locked Livewire properties. Missing bills cannot be verified. Queries and writes remain restricted to the fixed fictional portfolio and writes recheck local/testing plus the demo flag.

Dashboard totals reflect the saved status on the next render. Due-date alerts continue after verification because there is no payment state. Browser verification saved and reopened DEMO-201; its two clearly labeled browser-check notes remain in the local demo history.

There is no source PDF, upload, OCR or actual validation engine yet. This history is a local workflow exercise, without authenticated actor identity, approval policy or tamper-resistant retention; it is not a production audit trail. Current collection queries target the tiny fixed demo dataset and will need database-level filtering/pagination before larger portfolios.
