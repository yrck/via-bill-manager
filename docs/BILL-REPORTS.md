# Bill reports and CSV export

Implemented October 10, 2026. Workspace and bill register → Bill reports & CSV export.

The report defaults to January through the current reporting month. Choose inclusive start/end reporting months, an accessible property and a bill status. Months use YYYY-MM-01. Reporting month is the recorded expectation month, not an allocation of service dates, invoice dates or payment dates. Status uses the same receipt/grace semantics as the register, with the UTC day boundary.

The page and CSV call the same scoped report service. Each request fetches its rows once and totals that collection. Only current statement projections count; earlier revisions never add to totals. Received statements remain visible after schedule exclusion, while excluded unreceived expectations are omitted. Accounts without records are absent, so this report is not evidence of complete collection.

Current-charge totals are calculated in integer cents and separated by currency. Missing statements have unknown charges and blank CSV amount fields. Printed prior balances and amounts due appear per bill but are deliberately not totaled: balances can carry forward and duplicate across periods. No value establishes whether a bill is unpaid. Usage stays in its recorded kWh field; no commodity conversion or cross-commodity total is introduced.

CSV has one row per bill, including the generation timestamp, applied filters, bill ID, reporting month, property/account/provider, current version, printed amounts, service dates, expectation/grace dates and receipt/due dates. It uses UTF-8 with a BOM and standard CSV quoting. Formula-like text and numeric-looking identifiers with leading zeros or at least 15 digits receive a protective apostrophe. Monetary values remain numeric decimal strings, including credits. Consumers should treat account and invoice identifiers as text; the CSV is for review, not an agreed accounting import format.

Exports reauthorize on every download and use current data at that request. A correction between viewing and downloading can change results; no immutable report snapshot is claimed. Downloads stream already authorized/materialized rows, do not create public files and send private/no-store and nosniff headers. Manager customer-view downloads recheck manager eligibility, the preview session, customer membership and property grants, just as the existing source-document downloads do.

A request over 5,000 rows is rejected with guidance to narrow its scope; the report never silently truncates. It is a synchronous first slice. Large asynchronous exports will need execution-time authorization, expiring private artifacts and revocation-aware download checks.

No migration or historical data backfill is required. Tests use fictional records in in-memory SQLite and cover screen/CSV reconciliation, credits, missing amounts, corrections, currency separation, filters, CSV quoting/formula safety, authorization and the row limit.

Still planned under B08: charge-line exports, exception aging, trend reports, fiscal/service-date allocation, XLSX/PDF, original-document packages, saved reports, scheduled delivery and custom report acceptance. B05 accounting handoff and B06 recovery accounting remain separate work. This report does not settle the uncertainties in ACCOUNT-REVIEW-CHECKLIST.md.
