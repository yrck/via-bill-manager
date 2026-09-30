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

## Proposal follow-ups — September 30, 2026

Both the earlier vendor proposal and submitted VIA proposal were reviewed. They describe intended capabilities, not demonstrated workflows. R01–R24 remain open. The [development backlog](DEVELOPMENT-BACKLOG.md) retains unique information from each and records proposed implementation priorities.

| ID | Open question | Evidence needed / related backlog |
|---|---|---|
| P01 | Which validation examples are true rejection rules versus review warnings? Equality of total/new charges can be valid; how are same-day adjustments, gaps, overlaps and rebills treated? | Original/corrected bill pair, rule inputs and reason codes; R01/R02/R06, B01/B03. |
| P02 | Which approval steps and accounting integration actually support the AP promise? Which system is authoritative for payment? | Approved bill, permission matrix, accepted export/API payload, GL allocation and return/reconciliation sample; R03/R04, B05. |
| P03 | How is a billing discrepancy pursued and credited, and how is recovered value separated from potential/modelled savings? | Completed dispute with partial credit/refund, correspondence and reporting treatment; B06. |
| P04 | What is included in managed customer service, and what remains technical support? Which information can customers see? | Client-manager assignment, onboarding checklist, first-cycle acceptance, quarterly review/action list, support escalation and training/adoption definitions; B13. |
| P05 | Does an enterprise customer require SAML federation, Microsoft sign-in, enforced SSO or automated provisioning? | Identity-provider configuration requirements, multi-account identity mapping and deprovisioning example; R08, B10. |
| P06 | Which reports, custom outputs and distributions are essential? Can original PDFs be exported in bulk? | Five accepted outputs, typed Excel file, PDF/CSV totals, document package manifest and a scheduled-recipient example; R07/R09/R11, B08. |
| P07 | Which provider collection and market feeds are available to the replacement, and which modules are actually included? | Supported-provider/authorization inventory; healthy/failed connection; separate interval versus market feed contract and event definitions; R01/R20/R22, B07/B14/B18. |
| P08 | What data-lifecycle and operating commitments should this independently hosted product provide? | Approved support scope, hosting/retention inventory, restore test, customer export/offboarding example and access-window requirements; R11, B16/B17. Proposal claims alone do not resolve these. |
| P09 | Which enterprise budget and comparison calculations must be reproduced? | Accepted manual budget/variance, supply-rate history, model inputs/fit diagnostics, reforecast and benchmark cohort/coverage; R12/R14/R17, B11/B12. |

Keep customer-specific commercial terms and source proposal files outside Git. Resolve these with the workflow owner or authorized populated examples, and record evidence using the review-session format above.
