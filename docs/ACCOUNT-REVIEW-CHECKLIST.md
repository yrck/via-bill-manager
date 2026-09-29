# Second-account review checklist

Created September 29, 2026. All items below remain open until demonstrated on another authorized account or confirmed by the workflow owner.

**Evidence labels:** Function unclear = behavior or business rule is unknown. Data needed = interface was visible but lacked a representative populated example. Not exercised = submission, mutation, delivery, or output was intentionally not tested. Loading ambiguity = an initial blank/error state may have been temporary. These are not confirmed legacy defects.

## Priority 0 — defines the core product

| ID | Area / evidence label | What we know | What to establish next | Example needed |
|---|---|---|---|---|
| R01 | Invoice intake — Not exercised | Onboarding has a grid, PDF extraction, Process and Review steps; separate property/account bulk import exists | Supported sources/formats; extraction confidence; mapping; duplicates; partial failures; retry; manual correction; final publishing | Completed import with source file, one rejected row, one duplicate and its result history |
| R02 | Invoice audits — Function unclear + Data needed | Passed-audit and utility-estimated markers exist; invoice has Audits tab | Actual rule definitions, severity, configurable thresholds, failed-audit evidence, overrides, ownership and rerun behavior | Failed bill, estimated bill, duplicate, corrected/reissued bill |
| R03 | Review/approval — Function unclear | Statement Life Cycle tab exists; sampled bill had no client-visible events | Is there invoice approval? Who reviews, approves, disputes, releases or returns it? What does each status mean? | Bill with full lifecycle and users in different roles |
| R04 | Payables/accounting — Function unclear | Statement/due dates, amount due, new charges and past-due balance appear | Does the app pay bills, export AP data, or only analyze them? Where is payment status authoritative? How are credits and reconciliations handled? | Due bill plus completed accounting handoff/payment reconciliation |
| R05 | Missing bills — Function unclear | Missing Bills report and alert type exist | How expected billing schedules, grace periods, inactive accounts, split supplier bills and irregular cycles are modeled | Account with a genuinely overdue expected bill and a deliberately excluded account |
| R06 | Aggregation — Function unclear | Billing/calendar reporting and separate tenant, balance, allocation, virtual and inactive roll-up controls exist | Parent/child relationships; double-count prevention; calendar allocation; estimates/accruals; corrected bill precedence; fiscal dates | Property with all relevant account types and a reconciled source-to-report total |
| R07 | Reports — Not exercised + Loading ambiguity | 43 report names were listed; many filter screens inspected | Which are enabled/used, whether selections load reliably, outputs, save/share permissions, scheduled/batch behavior, Excel/PDF parity | Customer's five most-used populated reports with known totals and one scheduled report |
| R08 | Authorization — Function unclear | Only one demo user/organization inspected | Internal/external roles, organization/property/account grants, team assignments, reviewer rights and document/export access | Administrator, operator and restricted client views of the same portfolio |
| R09 | Documents — Data needed + Not exercised | Invoice Images has LDC/Supply tabs; sample viewer showed loading; property document list exists | Actual PDF rendering, image-to-statement mapping, versions, corrections, download permissions and retention | Bill with both delivery/supply PDFs and a revised attachment |
| R10 | Current-period dashboard — Data needed | Historical invoices exist; current-year charts can be empty | Reliable data-through dates, completeness denominator, freshness and whether zero is distinguished from unavailable | Portfolio with a completed month and an in-progress month |
| R11 | Export/migration — Function unclear + Not exercised | Several CSV/Excel/PDF export controls exist | Bulk historical export, stable IDs, document retrieval, API availability, integration rights and volume | Representative supported export with identifiers and linked documents |

## Priority 1 — defines later modules

| ID | Area / evidence label | What to establish next | Example needed |
|---|---|---|---|
| R12 | Budgeting — Function unclear + Data needed | Regression/control period, average/manual projections, tax/rate overrides, budget versions, approvals and actual variance | Completed budget with calculation inputs, approved revision and actuals |
| R13 | Property/KPI metadata — Data needed | Effective dates, historical occupancy, custom metric types, characteristic changes and compliance obligations | Property with changing occupancy, custom KPI and populated compliance tab |
| R14 | Supply contracts — Data needed | Split delivery/supply accounts, rate components, renewables, expiration alerts and contract-to-bill validation | Active/expired contracts, rate change and related bills |
| R15 | Emissions — Function unclear | Scope assignments, location/market methods, factor precedence/versioning, custom overrides, renewable adjustments and recomputation | Populated calculation trace with factors, dates, units and expected result |
| R16 | ENERGY STAR / Arc / Green Button — Data needed + Not exercised | Which connections are working, sync direction/cadence, source of truth, permissions, failures and repair | Healthy connection plus failed sync and history; credentials need not be displayed |
| R17 | Goals / benchmarking — Function unclear + Data needed | Baseline exclusions, target/trend projections, normalized goals, peer selection and data licensing | Goal with known baseline and a populated peer/internal comparison |
| R18 | GRESB and other sustainability portals — Data needed | Native workflow versus external link; data submission, survey mapping and validation | GRESB survey with assets and an accepted export/submission example |
| R19 | RECs — Function unclear + Data needed | Allocation, vintage eligibility, retirement evidence and double-count prevention | Certificate allocated across accounts with attestation |
| R20 | Live meters — Data needed + Loading ambiguity | Interval ingestion, timezone/DST, gaps, estimates, resampling, heat maps, daily/monthly profiles and repair | Meter with recent data, known gap, DST boundary and production/consumption readings |
| R21 | M&V — Function unclear + Data needed | Project lifecycle, actual baseline/regression, adjustments, savings start, revision and verification | Completed project with accepted baseline and monthly savings reconciliation |
| R22 | Peak Load — Function unclear | NYISO view loaded; other market variants not validated. Establish feed source, refresh, market definitions and alert delivery | Populated ISO-NE/PJM/ERCOT cases and a historical event notification |
| R23 | Alerts — Not exercised | Delivery recipients/channels, scope, schedules, deduplication, escalation and acknowledgement | Fired missing-bill/variance alert, recipient view and delivery record |
| R24 | Groups / dashboard profiles — Not exercised | Static/dynamic group membership, sharing, personal versus organization settings and filter propagation | Shared group and shared/personal dashboard used by two roles |

## Suggested review session

Start with a populated account containing recent bills, source PDFs and exceptions. Walk one bill from ingestion through review and accounting handoff, then reconcile that bill into a property report. This resolves R01–R10 before spending time on specialist modules. Prefer existing completed examples; any synthetic submissions should use an explicitly designated test area.

Record each result using: **ID · account/role · page · example period/record · observed behavior · confirmed rule · remaining question · evidence reference · reviewer/date**. Mark a feature unavailable, permission-restricted, unconfigured or unexplained only when the evidence supports that distinction. Keep credentials out of notes and screenshots.

A second account has not been reviewed yet. The dashboard proposal must not imply verified invoice approval, payment execution, collection automation or savings claims until the corresponding items are resolved.
