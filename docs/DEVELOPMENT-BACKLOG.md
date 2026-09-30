# Development backlog

Reviewed September 30, 2026 against the implemented Laravel application, the legacy UI audit, and both supplied proposals. This is the current development ordering; the earlier rebuild plan remains the broader inventory. All items below are planned unless explicitly described as partial. Proposal inclusion is evidence of intended value, not proof of working legacy behavior or a commitment that the replacement already delivers it.

## What the proposals change

The core direction remains collect → validate → review → resolve → report. The largest missing piece is the operational work around that flow: collection health, assigned exceptions, accounting handoff, documented recovery, and VIA's ongoing customer service. An invoice register alone does not deliver the proposed value.

Sources are retained privately by the user, not copied into this repository:

- **A — earlier vendor proposal:** `Houston ISD- Via-Tango proposal 2-20-2026.docx`. Particularly useful for reviewer/approval attributes, AP workflow, tax/closed-account errors, late fees, scheduled report distribution, adoption metrics, training and in-app support.
- **B — submitted VIA proposal:** `Houston Metro UBM Plaform Proposal.docx`. Particularly useful for departmental needs, dedicated client management and quarterly reviews, shared implementation progress, SAML SSO, custom reporting, detailed budget/variance methodology, and optional interval monitoring.

Both contribute requirements. B clarifies intended packaging; A retains useful details omitted from B. Customer-specific prices, dates, commercial terms and source screenshots are not reproduced here. The proposal's implementation timeline describes onboarding an existing service; it is not a replacement-software engineering estimate.

### Capability crosswalk

| Value / source section | Current application or prior plan | Backlog decision |
|---|---|---|
| Bill capture, validation and invoice detail — A/B Data Capture, Data Validation, Invoice Management | Manual USD electricity PDF intake, balances, kWh and basic duplicate/date checks work. Detailed charge classification, staging, OCR and provider collection are planned only. | B01, B04 and B07: make the whole ingestion process inspectable, including failed collection and human correction. |
| Review, approval and AP integration — A User Defined Alerts / Benefits; B What Sets Us Apart | Verify/reopen records a reviewer; approval and accounting integration are absent. R03/R04 already ask for evidence. | B05: independent approval and accounting handoff; verification must not imply approval or payment. |
| Billing errors and cost recovery — A Benefits; B Value to customer | Exceptions and potential/verified savings appeared in the dashboard concept, but no operational recovery workflow exists. | B03/B06: assigned cases, supplier follow-up and evidence-linked credit/refund recovery. |
| Bill charge categories — A/B Invoice Management | Current charges, balance and total are stored, but consumption, demand, fixed fees, taxes and miscellaneous line items are not. | B01: retain raw labels plus normalized categories, rates, quantities and units. |
| Alerts and missing data — A/B User Defined Alerts and auditing | Demo examples exist; customer intake does not establish a real recurring billing schedule or run audits. | B02/B03/B08: expectations, freshness, versioned rule evidence and reliable delivery. |
| Flexible organization and departmental views — A/B Dashboards, Asset Management, User Administration; B Value | Multi-account memberships, invitations, property grants and account switcher work. Hierarchies/groups and dashboard profiles are planned. | B09: saved department views over the same permission-scoped facts; no department gets wider access merely through a dashboard. |
| Standard, custom and distributed reports — A Reporting / Benefits; B Scope / Reporting | Report engine/exports were already planned, not implemented. | B08 adds explicit bulk original-PDF packages, scheduled distribution and a controlled custom-report request/acceptance workflow. |
| Budgeting, supply contracts and benchmarks — A inventory; B Scope / Budgeting & Variance | Existing later-phase plans cover these generally. | B11/B12 make basic budget vs actual and internal comparisons an enterprise readiness requirement when sold; statistical forecasts follow validated basic calculations. |
| SSO — B Scope / Single Sign On | Only email/password is implemented; Microsoft was deferred. | B10 adds organization-configured SAML federation explicitly. Microsoft sign-in alone does not satisfy every corporate SSO requirement. |
| Managed implementation and ongoing service — A Customer Success / Implementation; B Value / What Sets Us Apart / Timeline | Staff can administer customers and use recorded read-only customer views. There is no service-delivery workspace. | B13: assigned client manager, onboarding milestones, health checks, quarterly actions, support handoff and training resources. |
| Meter/peak intelligence — A Peak Load; B Interval Data / Peak Load / optional module | Smart Meter Texas, MISO/PJM and Timescale are future work. | B14 separates customer interval collection from market forecasts/peak alerts; manual interval upload remains a useful fallback. |
| Climate/sustainability and M&V — A/B executive value and reports | Already covered by the broader rebuild plan. | B15 retains scope, units, factors and baseline evidence; not a new MVP expansion. |
| Security, portability and continuity — A/B Security / Business Continuity; B service agreement | Application access checks and deployment packaging exist. Full operational controls, export/offboarding and recovery proof do not. | B16/B17 add explicit release gates and customer-data lifecycle work. Vendor hosting/certification claims do not transfer to this application. |
| Platform, capture and interval modules — A base/optional overview; B Scope / Order Form | No subscriptions or entitlements implemented. | B18 informs later free/paid design without adopting proposal pricing or equating a physical meter with a billable utility account. |

## Implemented baseline at proposal review

Registration creates a named customer account; invited users join existing accounts. Verification, recovery, multiple memberships, property grants and account switching are implemented. Eligible employees require an explicit manager grant. Customer-view mode remains recorded and read-only. Customers can create locations/accounts, upload and manually enter a USD electricity PDF, browse bills, download authorized originals, and verify/reopen statements with history. The workspace lists recent bills needing review.

The anonymous local-only demo has richer fictional queues and metrics; those are not authenticated customer capabilities. Current intake permits one statement per utility account/reporting month, rejects duplicates and reconciles current charges plus prior balance to amount due. It has no statement corrections, line-item model, automated audit engine, recurring schedule configuration or AP status. Status evidence: `app/Services/BillIntake.php`, `app/Services/CustomerBillReview.php`, `app/Http/Controllers/CustomerWorkspaceController.php`, feature tests, and README.

## Priority 0 — complete the dependable bill-management loop

Priority is implementation order, not a promise of delivery dates. Each item must preserve organization/property scoping, manager identity and read-only preview, actor history, original evidence, and safe concurrent updates.

### B01 — Statement corrections and detailed charges (partially implemented)

September 30 increment: reasoned corrections, historical snapshots/PDFs, version-specific review history, stale-write protection and optional reconciled charge lines are implemented. Current totals use the latest statement only. See [statement history](STATEMENT-HISTORY.md). Voiding, multiple independent monthly bills, split supplier reconciliation and explicit payment components remain open. The earlier baseline/crosswalk records the proposal-review state before this increment; the conditions below describe the complete epic.

- Introduce explicit original/revised/void relationships, effective version selection, correction reasons and retained PDFs. Support legitimate multiple statements within a month and split supply/delivery relationships without double counting.
- Capture line-item description, category, quantity/unit, rate, amount and source reference. Categories include consumption, demand, fixed charges, taxes, late fees, deposits and other adjustments. Preserve unknown categories for review.
- Separate current-period expense, prior balance, payments/credits printed on a statement, and payable amount. Printed payment activity is not authoritative payment reconciliation.
- **Done when:** an original and corrected sample reconcile to source; totals include only the intended version; old evidence remains accessible; duplicate retry is harmless; a statement with zero prior balance passes. Review approval must be reconsidered when its source version changes.
- **Dependencies/evidence:** R01/R06/R09; obtain a corrected bill, demand/tax detail and split supplier example. ESI IDs remain strings on service points, separate from billing accounts and replaceable physical meters.

### B02 — Expected bills, collection coverage and actionable customer dashboard

September 30 increment: owner-configured effective monthly rules, grace periods, stop/resume history, repeatable generation and selected-month coverage are implemented. Customer and read-only manager views include missing and review queues. See [billing schedules](BILLING-SCHEDULES.md). Irregular cycles, split supplier expectations, due-soon/payment context and provider-connection freshness remain open.

- Configure effective-dated billing cadence, grace periods, inactive/closed accounts, separate supplier expectations and irregular billing. A new utility account or imported bill must not invent a recurring schedule.
- Show missing/awaiting bills, review backlog, due-soon items and stale collection with visible scope, date basis and coverage denominators. Link each count to its underlying queue. Unconfigured expectations display unknown coverage.
- **Done when:** a completed month and an incomplete month reconcile with their schedules; exclusions and late arrivals update counts correctly; due dates never produce an unsupported “unpaid” claim. Collections, financial charges and payable balance stay separate.
- **Dependencies/evidence:** R05/R10; B01 version selection for correct counts.

### B03 — Validation findings and assigned exception cases

- Distinguish blocking ingestion errors from warnings requiring review. Start with duplicate/overlap/gap evidence, balance reconciliation, abnormal duration, usage/day and cost/day changes. Add demand/load-factor checks only when the required measurements exist.
- Extend to late fees, charges after account closure and tax/rate discrepancies once effective dates, contract/exemption evidence and domain-approved rules exist. A rule identifies a potential issue, not a guaranteed refund.
- Cases need assignee, severity, next action/due date, comments, source bill/version, related findings, resolution reason and reopen history. Rules need versions, thresholds, comparison windows and insufficient-history outcomes. Reruns must not duplicate cases or erase decisions.
- Add configurable missing-bill, variance and deadline notifications with scoped recipients, timezone-aware schedules, deduplication, acknowledgement/escalation and delivery history. Recheck recipient access when sending; distinguish a failed delivery from an unacknowledged issue. Start with email/in-app channels and evidence links.
- **Done when:** a reviewer can explain a flag using its inputs and comparison bill, assign it, resolve it with evidence, and see a correction trigger reevaluation. A missing baseline cannot silently pass an audit.
- **Dependencies/evidence:** B01/B02 and R02/R23. The advertised audit count is not a specification; approve named rules individually.

### B04 — Staged bulk intake and assisted extraction

- Add customer-scoped spreadsheet imports and PDF extraction into drafts; show field confidence/source location, account mapping, rejected rows and explicit review/publish steps. Preserve raw input and parser version.
- Provide job progress, partial-failure summaries and idempotent retries; keep invalid drafts out of published totals. Extend commodities and original units using representative gas/water/steam/waste samples, rather than storing everything as kWh.
- **Done when:** a mixed valid/invalid/duplicate batch can be corrected and retried without duplicate statements; reviewers can trace published values to originals; revoked access prevents publication/download.
- **Dependencies/evidence:** B01/B03, R01/R09. Enable workers and operational job monitoring when asynchronous processing is introduced.

### B05 — Approval and accounting handoff

- Define approver permissions, return/dispute states and separation of duties with the pilot workflow owner. Keep data verification, authorization for payment, export status and payment reconciliation separate.
- Start with a versioned CSV/AP export contract: vendor/account mappings, cost center/GL allocation, totals, source document references, approval evidence and batch ID. Add an ERP adapter only after the target and return format are agreed.
- Record rejected/accepted handoffs, retries and externally supplied payment status with provenance. Do not build payment execution into the MVP.
- **Done when:** an approved sample balances to an AP batch; a retry cannot silently submit it twice; corrected/superseded bills cannot slip into an old approval; returned payment information identifies its authoritative source.
- **Dependencies/evidence:** B01/B03/B08, R03/R04; obtain a real accepted accounting file and allocation example. Allocation fields are a proposed design needing validation.

### B06 — Recovery and value evidence

- Track suspected overcharge → investigated → supplier disputed → accepted → credited/refunded, with owner, correspondence references, dates, affected bills and evidence of settlement. Allow partial recovery and rejected claims.
- Keep potential, confirmed and realized amounts separate. Link a credit/refund once and prevent repeated counting across cases, bills and periods. Energy avoidance and modeled savings belong to a separate baseline methodology.
- **Done when:** a partial credit can be traced back to the original case and source document; summary totals distinguish unresolved opportunity from realized recovery. Add processing-time and late-fee trends with a defined baseline, not assumed savings.
- **Dependencies/evidence:** B01/B03/B05 and a resolved billing dispute. This makes the earlier dashboard savings concept operational.

## Priority 1 — automation and enterprise delivery

These are required before offering the corresponding enterprise features. Their priority does not imply they can be omitted from an account sold those capabilities.

| ID | Deliverable | Completion criteria / dependency |
|---|---|---|
| B07 | Authorized provider collection and connection health | Begin with a named provider or supported aggregator. Track consent, covered accounts, credentials in protected storage, last attempt/success, expected next bill, failure reason, reauthentication and retry. Preserve source PDFs and deduplicate through B04. Test expired authorization, partial collection and account closure. Provider coverage is verified individually; market feeds do not collect invoices. |
| B08 | Reports, document packages and delivery | Start with invoice/line-item data, missing/estimated bills, exception aging, balances and location cost/usage trends. Reconcile screen/CSV/XLSX/PDF totals under identical scope, calendar/fiscal basis and unit conversions. Add bulk original PDFs with a manifest, saved parameters and scheduled delivery with timezone, recipient permissions, retries and delivery history. Recheck access during job execution and download. Custom report requests have an owner, versioned definition, reference totals and acceptance record; no full report designer required initially. |
| B09 | Hierarchy, groups and department dashboards | Add effective portfolio groups and saved personal/shared scopes. Offer Operations, Finance and Leadership presets: completeness/work, approvals/budget, and outcomes respectively. Filter each through actual permissions and show freshness. Start with presets and saved filters; arbitrary widget layouts follow demonstrated need. |
| B10 | Enterprise identity and permission extensions | Keep public email/password and invitations. Plan Microsoft linking plus per-organization SAML identity-provider setup where required, tested with a real tenant. Define identity linking, account discovery, revocation, enforced SSO and recovery without granting access by email domain. Add staff MFA and explicit approver/support capabilities; SSO never grants platform management. No SCIM commitment without a demonstrated provisioning need. |
| B11 | Supply contracts, budgets and variance explanation | Begin with effective-dated supply terms, expiration reminders and versioned manual/monthly budgets vs actuals. Separate usage, demand, delivery/supply rates and taxes. Later add weather/occupancy inputs, fixed/index/hybrid products, licensed forward-price assumptions, model-fit diagnostics, approved versions and reforecast snapshots. Demonstrate totals and driver attribution against an accepted sample; insufficient history must be visible. Depends on B01/B08 and R12/R14. |
| B12 | Comparable facility performance | Internal cost/usage/demand comparisons first, using effective area/occupancy and comparable periods/coverage. Preserve native units and documented conversion factors. External benchmarks need validated licensing, cohort and integration rules. Ranking must explain exclusions and avoid treating missing data as efficiency. Depends on B08/B09 and R13/R17. |
| B13 | VIA service-delivery workspace | Assign a client manager; track onboarding tasks, data access, migration reconciliation, configuration, training and first-billing-cycle acceptance. Add portfolio health, unresolved data issues, quarterly review dates, recommendations, customer-visible actions and internal notes with separate visibility. Link a support/ticket system and training resources rather than building a help desk or LMS. Track adoption milestones with defined metrics. All customer writes by staff need a separately authorized, recorded workflow; read-only customer-view stays read-only. |

B13 enables the people delivering account management; it does not automate their judgement, guarantee round-the-clock staffing, or replace technical incident response. The submitted proposal describes an existing third-party-supported service; the new application needs its own named support owner and escalation route.

## Specialist modules and public-launch gates

| ID | Deliverable | Completion criteria / dependency |
|---|---|---|
| B14 | Interval data and market-specific peak alerts | Separate customer meter feeds (including Smart Meter Texas) from ISO/market load forecasts (including requested MISO/PJM). Store consent, stable service-point mapping, meter changes, timezone/DST, units, consumption vs onsite generation, missing/estimated intervals and import lineage. Support manual templates, heat maps and daily/monthly profiles. Validate each market's event definition, forecast freshness, notification timing and measured response; never apply a blanket capacity-savings formula. Peak notices can be useful without purchasing interval monitoring, while customer-specific impact needs its own data. Benchmark Timescale only for interval storage; core business tables stay PostgreSQL. R20/R22 remain open. |
| B15 | Sustainability and verified project outcomes | Retain existing plans for emissions/factor provenance, renewable allocation, goals, ENERGY STAR/other integrations and M&V. Tie climate progress to versioned baselines, coverage, approved methodology and evidence. A report name or external link does not establish an integration or verified savings. Dependencies: trusted B01/B08 data and R15–R21. |
| B16 | Production operations and security evidence | Before public launch, verify email delivery, staff protection, private document handling/scanning, application/export isolation, audit event coverage, job monitoring, incident handling and restoration of database + documents + keys. Record actual hosting region, encryption controls, retention and measured recovery objectives. Test restore and failed-job recovery. Coolify packaging is not evidence of HA, an uptime commitment, PostgreSQL RLS or SOC 2. Existing application-level query scoping must remain enforced. |
| B17 | Customer data portability and offboarding | Add authorized export of structured data and originals with stable IDs/manifests. Define suspension, end-of-service export access, stopped collection, retention holds and approved deletion separately. Show request/status history; deletion must account for documents, backups and integrations. Prevent background work after access/consent removal. Validate policy and export-window requirements with the service owner before implementation; do not copy vendor deadlines. |
| B18 | Subscription and service entitlements (planning only) | Evaluate a self-service core, paid automation/analysis, optional interval module and managed service. Define billing unit: organization, utility account, service point or active physical meter; test replacements, inactive accounts and split supplier bills. Keep module entitlement separate from authorization, with clear downgrade/export behavior. Record contractual overrides without hard-coding proposal terms. No prices, limits, charging or automatic suspension selected yet. |

## Assumptions to correct or validate

1. **A proposal is not a validated rulebook.** Both documents list equality between total/new charges among import problems. Equality can be valid with no previous balance. Gaps/overlaps can need warnings or correction handling rather than blanket rejection. Same-day dates require an agreed period convention and adjustment-statement policy.
2. **Market and ROI claims require separate evidence.** Preserve useful peak-alert and recovery features without using sales percentages, generic ROI periods or modeled avoidance as actual customer savings. This review does not validate external studies or market rules.
3. **Counts are not acceptance criteria.** The earlier UI inventory lists more report names than the proposals advertise; neither supplies complete definitions. Select required outputs and audit rules using examples, not a target count.
4. **Enterprise scope is broader than the internal pilot.** Keep the staged core build, but explicitly gate any equivalent enterprise offering on its included SSO, collection, reports, budgets, benchmarks and peak services. Three internal electricity meters cannot validate multi-commodity enterprise readiness.
5. **Privacy and hosting descriptions need our own evidence.** This app holds names, emails and bill documents. Do not adopt statements that no personal data is collected, that privacy obligations are generally inapplicable, or that another provider's controls apply to Coolify. These are product/operations questions, not legal conclusions.
6. **Sources contain differing service promises.** Support coverage, availability language and delivery timelines vary across narrative and service terms. Record our own approved service commitments before surfacing them in product settings or sales materials.

The review questions are recorded in [ACCOUNT-REVIEW-CHECKLIST.md](ACCOUNT-REVIEW-CHECKLIST.md); none of R01–R24 is closed by these proposals.

## Next implementation sequence

1. B01: corrected/versioned statements and charge detail, using private pilot bills plus a corrected sample.
2. B02/B03: expected schedules, explainable findings and an assigned customer work queue.
3. B08 core exports, then B05 accounting handoff and B06 recovery evidence using agreed examples.
4. B04/B07: batch/extraction/collection automation, and B13 onboarding/health visibility as customers grow.
5. Plan B10–B12/B14–B18 against the first external customer's scope. Public-launch controls are release gates throughout, not cleanup deferred until after launch.

This review changes the backlog only. It does not enable integrations, alter customer records, adopt contractual commitments, or establish a feature-complete replacement.
