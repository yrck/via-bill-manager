# Via Energy — product audit and rebuild plan

Reviewed September 29, 2026 through the signed-in Tango E&S demo UI.

September 30 update: the [development backlog](DEVELOPMENT-BACKLOG.md) compares both supplied proposals with current implementation and supplies the current delivery order. This document remains the historical UI inventory and broad replacement scope; its original phase estimates are not a new delivery commitment.

Follow-up documents: [Second-account review checklist](ACCOUNT-REVIEW-CHECKLIST.md) records unverified functions and required examples. [Laravel and dashboard proposal](DASHBOARD-AND-LARAVEL.md) aligns the foundation with Via Contract Manager and defines an actionable dashboard.

## Recommendation

Build a modern utility-data operations product around one dependable workflow: collect bills, validate them, resolve exceptions, and explain portfolio cost and consumption. Expand into forecasting, sustainability, and interval intelligence once the underlying data and calculations are trusted.

The existing application is a substantial energy-management suite. A visual refresh is a smaller project than replacing its data collection, calculation rules, integrations, and reporting. This plan assumes a new application with a migration path; it does not assume access to Tango's source code, database, APIs, or contractual integration rights.

## Scope and confidence

I visited all eight primary navigation modules, the administrative destinations exposed by the user menu, representative property/account/meter/project detail pages, and the report catalog. The home dashboard displayed 78 properties, 860 accounts, and two supply accounts. Those are demo UI counts, not an independently verified migration inventory.

This is a page-family and workflow audit, not an exhaustive test of every record, conditional form, report output, or permission level. Several pages initially showed loading placeholders; the supplied analytics URL briefly exposed a TypeError and subsequently rendered. Some report selections did not visibly change the active report. Empty charts often defaulted to 2024–2026 while historical invoices existed in 2018–2021. These observations should not be interpreted as proof that every empty screen is broken.

No business records were intentionally created, edited, deleted, imported, or submitted. Creation forms were inspected without saving. External reporting portals were cataloged without signing into them. Credentials are excluded from this document.

## Page and feature inventory

| Area visited | Observed functionality | Implication for the new version |
|---|---|---|
| Home | Shared dashboard selector, portfolio counts, electric performance, emissions equivalencies, recent updates, building map, commodity performance comparisons | A configurable overview with visible date ranges, data freshness, coverage, and drill-downs |
| Invoice Management hierarchy | Organization → region → locality → property → commodity → account; custom groupings; global search; breadcrumbs | Preserve hierarchy and flexible groups, but add searchable inventory tables and direct links |
| Property analytics | Usage/cost chart; monthly, quarterly, fiscal, annual views; emissions toggle; period comparisons; summary export and map | One consistent analytics workspace with understandable units and filter scope |
| Property characteristics | General location and square footage; Characteristics; ENERGY STAR; LEED Arc; Compliance | Effective-dated asset metadata and integration status; some integration tabs were sparse during inspection |
| KPI Metrics | Occupancy chart with total/reported/occupied/forecast square footage; adjust occupancy; custom KPI management | Time-varying denominators for comparable performance calculations |
| Documents | Upload, created date, uploader, PDF view, download, deletion controls | Documents linked to properties, accounts, bills, contracts, and their audit history |
| Account invoices | Historical billing rows, usage, delivery cost, supply cost, total cost, audit status, estimated-bill marker, notes; usage/day, cost/day, degree-day, unit-cost charts | Fast invoice register with explicit quality flags, filters, and document/detail views |
| Invoice detail | Line Items, Audits, Statement Life Cycle, Invoice Images; statement and due dates, invoice number, period, balances, charges, consumption, provider | A single invoice review workspace with source evidence and change history |
| Account characteristics | Account type, utility and supply identifiers, provider, commodity, ISO, rate class, unit, location; ENERGY STAR | Model utility accounts separately from physical meters and supply agreements |
| Supply contracts | Vendor, term, execution date, rate, billing type, energy/capacity/ancillary/transmission components, renewable percentage and certification | Effective-dated contract terms that can feed forecasting and emissions |
| Emissions configuration | Scope 1, Scope 2 location, Scope 2 market, Scope 3 rates; dates, units, source and rate type | Versioned factors and traceable calculation provenance |
| Reporting | 43 catalog entries including Sentence Filter; property/account/commodity/date filters; saved, batch, monthly groups; Excel/PDF controls; roll-up status | Shared report engine with useful presets, saved views, and reliable exports |
| Sustainability / Goals | Goals table, baseline/target years, metric, assets, commodities, absolute reduction, performance/status; yearly/monthly goal detail with actual/goal/trend/target series | Goal tracking grounded in reproducible baseline and coverage rules |
| Sustainability / ENERGY STAR | Score, ESPM ID, energy/water currency, emissions, EUI, approval/certification dates, alerts, export | Benchmark synchronization with visible freshness and error handling |
| Sustainability / comparisons | Anonymized peers, DOE Building Performance Database, source/site EUI; internal usage/cost/demand/emissions comparisons with KPI and date choices | Distinguish internal calculations from licensed or external benchmark datasets |
| Sustainability / reporting | GRESB survey/asset interface; links to CDP, SASB, GRI, ESPM, Arc, Science Based Targets, RE100, TCFD | An external portal link is not evidence of a working integration; validate each requirement |
| Unbundled RECs | Vendor, vintage, volume, contract and attestation documents | Certificate allocation and evidence management; verify retirement/allocation rules in discovery |
| Budgeting | Searchable budget list, export, filters/display settings; creation by property/account/fiscal year/name/date range | Versioned budgets and comparison against actuals |
| Advanced budget setup | Monthly invoice regression, average invoice data, manual usage/demand; control period; taxes and supply-rate overrides | Forecasting is a separate calculation workstream, not just a table UI |
| Real Time Monitoring | Map/time range, meter list, meter creation, data export, alert indicator | Separate interval-data ingestion and storage concerns from invoice ingestion |
| Meter detail | Monitoring, Analytics, Characteristics, Data Issues; interval chart with weather, demand, peak and capacity overlays; consumption/peak/min/average summaries | Timezones, daylight saving, missing intervals, and unit semantics are core requirements |
| Meter analytics | Heat Map, Monthly Profiles, Daily Profiles | Useful specialist views; some screens had insufficient loaded data to validate output |
| Measurement & Verification | Project list with classification, stage, dates, cost, measurement type, projected savings; project details and tracking action | Project lifecycle plus baseline/adjustment/savings methodology; calculations not independently verified |
| Peak Load | NYISO, ISO-NE, PJM Capacity, ERCOT selectors; NYISO actuals, forecasts, threshold bands and peak tables | Market-specific feeds and definitions require a separate integration scope |
| Alerts | Existing variance alert; creation types include budget approval, missing bill, new invoice, past-due balance, demand event, meter threshold, supply contract and data-update reminders | Central rules engine, scoped recipients, deduplication and delivery history |
| Custom Groupings / Data | Group creation entry point and KPI management entry point | Shared portfolio segmentation and metric definitions |
| Imports | Bulk Imports / Properties and Accounts page | Separate master-data import from bill import; detailed import processing not exercised |
| Onboarding | Separate invoice-import app: Enter Data → Process → Review; spreadsheet grid, PDF Data Extraction, optional columns, save/import controls | Guided ingestion with staging, validation, review and import results |
| Integrations | Arc Integration and Green Button Connect entry points | Adapter layer and per-connection status; authentication not exercised |
| Settings | Fiscal year, area units, email preferences, account roll-ups, custom emissions, ENERGY STAR sync start period; meter-provider credential sections | Separate personal preferences from organization-wide calculation and integration settings |
| Widget Profiles | Profile list and add-profile action | Saved dashboard configurations; do not prioritize elaborate widget editing ahead of core workflows |

## Product changes worth making

1. **Give users an actionable home screen.** Show missing bills, unusual charges, failed imports, expiring contracts and stale data, with owners and next actions. Keep executive performance summaries nearby.
2. **Make invoices accessible across the entire portfolio.** The current hierarchy is useful context, but users should not need to descend through several levels to find a bill. Provide search, saved filters and bulk review.
3. **Keep scope and dates visible.** Display selected properties, commodities, reporting basis, estimates policy and data-through date on charts and exports. Offer “latest available data” for historical/demo portfolios.
4. **Distinguish zero, missing, estimated and partial.** A no-data chart should explain coverage and offer a useful date range. Never render NaN values as ordinary readings.
5. **Make every number traceable.** Drill from portfolio totals to accounts, bills, line items and source documents. Explain exclusions, conversions and adjustments.
6. **Use consistent interaction patterns.** Standardize tables, filters, drawers, forms, date pickers and chart exports. Label icon-only actions and support keyboard use.
7. **Make processing explicit.** Long imports, reports and recalculations should show queued/running/completed/failed states with retry and error detail. A raw exception should never be the main page content.
8. **Separate configuration by impact.** A personal setting should not silently recalculate emissions for the whole organization. Show scope, calculation version and completion state.

## Proposed navigation

Overview · Bills & Exceptions · Portfolio · Analytics & Reports · Budgets · Sustainability · Meters & Peaks · Projects · Administration.

Use a persistent organization/property scope selector and global search. Property pages should bring together overview, accounts, bills, metadata, KPIs and documents. Account pages should preserve supplier/delivery relationships without making users navigate duplicate data trees.

## First production release

| Epic | Deliverable | Acceptance condition |
|---|---|---|
| Identity and portfolio | Organization-scoped roles; property/account inventory; hierarchy; custom groups; search | A user sees only authorized assets; entity links remain stable after renaming |
| Invoice intake | Structured file/manual import and source-document upload; mapping, staging, validation and retry | Reprocessing the same input does not create duplicate bills; rejected rows explain how to fix them |
| Bill review | Portfolio invoice register, detailed charges, document preview, notes, estimated/corrected states, audit history | A reviewer can trace a displayed amount to its source and identify who changed it |
| Exceptions | Missing/duplicate/overlapping bills, unusual usage/cost, unmapped accounts and missing fields | Each exception has evidence, state, owner and resolution history |
| Analytics | Cost/usage trends, year-over-year comparisons, commodity and property drill-downs | Chart/table/export totals match under identical scope and date rules |
| Core reports | Inventory, invoice data, missing bills, estimated bills, balances, facility comparison and usage/cost trends | Saved report parameters are reproducible; exports include date/scope/unit metadata |
| Operations | Import-job visibility, basic alerts, monitoring, backups and migration reconciliation | Operators can diagnose failed work and safely retry without silent data loss |

Start with the commodities and ingestion sources actually needed by the pilot customers. The underlying model should permit additional commodities and units. Automated bill extraction can assist intake, but extracted fields must carry confidence and source references and pass validation before publication.

Defer sophisticated regression budgets, benchmarking integrations, REC allocation, live-meter feeds, peak forecasting and M&V savings engines unless a launch customer requires them. Payment execution was not established by this audit and is outside this proposed release.

## Architecture and data requirements

Use a modular application with one shared transactional data model, background workers for imports/reports/recalculation, object storage for source files, and a distinct interval-data ingestion path. Choose the exact framework, hosting and vendors after confirming the existing infrastructure, team preferences, data volume and integration rights. A microservice split is not needed merely to mirror the menu.

Core records: organization, user/membership/role, hierarchy node, property, group membership, utility account, physical meter, vendor, supply agreement, invoice/statement, line item, document, audit finding, import job, metric definition/value, calculation version and event history. Later records include budgets/scenarios, factors, emissions results, goals, RECs/allocations, interval readings, peak events and M&V projects/baselines.

Keep account identifiers as text. Store money in decimal types. Retain original quantities and units alongside normalized values. Preserve effective dates for account status, ownership, square footage, occupancy, contracts and emissions factors. Raw imported evidence should remain available after corrections.

The calculation layer must explicitly define billing period versus calendar allocation, fiscal years, supply/delivery separation, estimates and accruals, corrections/credits, overlaps, missing periods, unit conversion, weather normalization and KPI denominators. Roll-up inclusion is particularly important: the current report UI separately identifies tenant, building-balance, allocation, virtual-invoice and inactive accounts. Their relationships must prevent double counting.

Protect tenant boundaries in every query and export. Apply permissions to ingestion, editing, approvals, configuration and downloads. Store integration secrets outside ordinary application records; maintain audit events and restore-tested backups. These are implementation requirements, not assertions about the legacy system's controls.

## Delivery phases and planning ranges

These are preliminary engineering estimates, not a quote. They assume a dedicated small team of roughly three engineers with product/design support, a utility-domain owner and QA capacity, plus access to representative exports and documented data rights. Parallel work is possible; integration delays and poor source data can dominate the schedule.

| Phase | Indicative duration | Exit gate |
|---|---|---|
| 0. Discovery and migration proof | 2–3 weeks | Agreed workflows, roles, report priorities, calculation glossary, source-data inventory and successful sample extraction |
| 1. Prototype and foundation | 3–4 weeks | Tested navigation and invoice-review prototype; organization/permissions/data model; working import skeleton |
| 2. Core bill-management MVP | 6–9 weeks | One end-to-end path from import through review, exceptions, dashboard and export |
| 3. Pilot and cutover | 3–4 weeks | Reconciled migration, user acceptance, performance/access checks, operational runbooks and rollback rehearsal |
| 4. Budgeting and sustainability | 6–10 additional weeks | Accepted forecasting and emissions methodology; required integrations and reports validated |
| 5. Interval monitoring, peaks and M&V | 8–12+ additional weeks | Feed reliability, time handling, specialist calculations and workflows validated |

Allow approximately **14–20 weeks for a focused production pilot** under these assumptions. A broad replacement is more plausibly **6–12+ months**, depending on integration reuse, extraction coverage and calculation parity. UI prototyping can happen much sooner; it is not evidence that the backend replacement is complete.

## Migration and verification

1. Inventory export mechanisms, identifiers, row counts, documents, historical periods and source-system permissions. Establish who can supply the data before committing to full parity.
2. Map each source entity and preserve source IDs. Resolve duplicate vendors/accounts, malformed units and orphaned records in a reviewable staging area.
3. Build a representative reference dataset across commodities, split supply/delivery bills, estimated bills, credits, corrections, gaps, occupancy changes and fiscal boundaries.
4. Reconcile record counts and money/usage totals by account, property and period. Separate expected methodology changes from migration defects; agree rounding tolerances in advance.
5. Run a pilot portfolio alongside the existing product for at least one complete billing cycle. Check dashboard/report/export consistency and document links.
6. Freeze or capture changes for final sync, rehearse rollback, train users, and retain read access to historical evidence after cutover.

Critical tests cover cross-organization access, repeat imports, overlapping/corrected bills, calendar allocation, leap years, unit conversions, empty versus zero values and report parity. Later phases add DST/interval-gap cases, emissions-factor versioning and baseline/regression validation. Performance targets should be agreed against representative production volumes; the demo's counts are only a starting point.

## Decisions needed before a committed build estimate

- Is the initial buyer an internal Via team, property managers, external clients, or all three?
- Which five workflows and reports account for most daily usage?
- How do bills arrive today: manual entry, spreadsheet, email/PDF, utility feeds, or a service provider?
- Can historical data and invoice images be exported through supported mechanisms, and may the new application reuse each external feed?
- Are invoice approvals, payment/accounting handoff, client billing and supplier procurement required? Their full workflows were not established here.
- What must match the existing calculations exactly, and what should change?
- Which tenant/group permissions and existing enterprise login requirements must be retained?

The next concrete deliverable should be an agreed MVP backlog and a clickable prototype of Overview → Exceptions → Invoice review → Property analytics, using a representative exported dataset. That will expose the most important design and migration questions before committing to the specialist modules.

## Report catalog observed

Sentence Filter; Account, Property and Meter Inventory; Bill Component Report; Bill Entry Report; Bill Line Items; Bills Overdue; Budget Export; Budgeting - Aggregated; Competition Report; Comprehensive Budget Variance; Consumption Profile - Line Graph; Cost Profile - Line Graph; Demand Profile - Line Graph; Emissions Summary Report; ENERGY STAR Report; Estimated Statements; Facility Comparisons; Four Quadrant Executive Summary; GRESB Template; ICAP/PLC Trends; Invoice Data; Invoice Image Batch Download; KPI; Missing Bills; M&V Performance; Outstanding Balances; Portfolio Analysis; Real Time Actual vs. Guaranteed Production; Real Time Base Load Report; Real Time Monthly Interval Data; Real Time Peak Load Report; Simple Budget Variance (System); Supply Contract Details; Three Year Comparison Report; Trend Analysis; Unit Conversion and Emission Factor Report; Unit Cost Trend; Usage Comparison Report; User Activity; Utility Variance; Waste Recycling Diversion; Weather Report; YOY, YTD, MOM Overview.

Catalog presence is confirmed. Selection was attempted across the catalog, but not every item visibly activated, and generated output parity remains unverified. Do not treat all 43 as tested or as mandatory independent screens in the replacement.

## Source entry points

- [Original property analytics](https://tango.utilitydatamanagement.com/invoice-management/via-energy-demo/1466-maia-estates-copy//analytics)
- [Reporting](https://tango.utilitydatamanagement.com/reporting)
- [Sustainability goals](https://tango.utilitydatamanagement.com/benchmark/goaltracking/goals/)
- [Budgeting](https://tango.utilitydatamanagement.com/budget/)
- [Real Time Monitoring](https://tango.utilitydatamanagement.com/realtime/)
- [Measurement & Verification](https://tango.utilitydatamanagement.com/measure/)
- [Peak Load](https://tango.utilitydatamanagement.com/peakload)
- [Invoice onboarding](https://tango.utilitydatamanagement.com/onboarding/)
- [Settings](https://tango.utilitydatamanagement.com/userSettings)

These links require an authorized session. This document describes observed UI and proposed product requirements; it does not establish the internal implementation or verify external reporting standards.
