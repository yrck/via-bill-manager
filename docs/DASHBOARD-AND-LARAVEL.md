# Laravel foundation and bill-management dashboard

Proposal, September 29, 2026. This updates product direction, not application implementation.

## Current identity direction

The user clarified that this is a public customer-facing SaaS product with customer accounts and a separate VIA customer-management suite. Start with email/password authentication; Microsoft sign-in is optional later. The prior project remains a technology/deployment reference, not the identity model for this product. See [access control](ACCESS-CONTROL.md).

## Build on the prior Via foundation

Local Via Contract Manager manifests confirm Laravel ^13.17, PHP ^8.3, Livewire ^4, Tailwind ^4 and Vite ^8. Its README and deployment files describe PostgreSQL and Docker Compose app/worker/scheduler services. Its project instructions document Microsoft or Keycloak/OIDC sign-in, explicit account grants, scoped visibility, private documents, permanent audit history, and separate activity tracking. Some top-level documentation describes earlier scope; this is not a fresh audit of every contract-manager feature.

Use the same technology and operational conventions for a separate Via Bill Management application and database. Adapt proven identity, document storage, authorization, queue, logging and deployment patterns after checking their tests and dependencies. Sharing an identity provider does not automatically grant access to the new app. Do not copy secrets, contract records, role assignments or contract-domain migrations.

| Layer | Proposed use |
|---|---|
| Laravel + Livewire | Portfolio tables, filters, invoice review, assignments and administration |
| PostgreSQL | Organizations, accounts, expected bills, versioned invoices/charges, findings and reconciled summaries |
| Private file storage | Source PDFs, import files and generated reports behind scoped access |
| Queue workers | Ingestion, extraction, audits, roll-ups and report exports; progress/failure records visible to operators |
| Scheduler | Expected-bill checks, reminders, connector runs and refreshes |
| Small client-side chart components | Interactive usage/cost charts; server-authoritative calculation and permission checks |
| Docker Compose | Isolated app, workers, scheduler and database; follow the prior Docker-based PHP workflow |

Laravel's [queue documentation](https://laravel.com/docs/13.x/queues) supports background processing, batches and failure/retry handling. [Livewire 4](https://livewire.laravel.com/docs/4.x/quickstart) supplies the interactive server-rendered UI foundation. Exact dependencies should be locked and tested when scaffolding, rather than treating the prior project's ranges as a permanent compatibility guarantee.

Start with one modular Laravel application. Keep utility calculations in independently testable services, not Livewire components. Organize around Portfolio, Ingestion, Billing, Exceptions, Reporting and Identity. Put later forecasting, sustainability and interval processing behind distinct module boundaries. A possible future link to Contract Manager should use explicit contract references/API integration, not shared table writes.

## The dashboard's job

Within a minute, an operator should know: **What is missing? What needs review? What is due soon? Can this billing period be trusted?** Every summary should open the records that produced it.

The default operator layout puts the prioritized work queue first. A second layout emphasizes period completeness for a portfolio manager. Both use the same definitions and access-scoped data. Role-appropriate defaults can come later; avoid an elaborate custom widget builder in the MVP.

### 1. Persistent context

Organization/property scope, billing period, data-through timestamp and definition of the date basis. Show “latest available” when a historical portfolio has no current data. Changing period/scope changes summaries and worklist together. Operational due dates have an explicit as-of date so they are not confused with the billing period.

### 2. Action summaries

- **Missing bills:** expected statement instances past their expected receipt date plus grace period, with no accepted matching bill. Accounts with no established schedule are “schedule unknown,” not missing. Track delivery and supply separately when required.
- **Needs review:** distinct received invoices with unresolved review findings. An invoice with three findings counts once here; show finding count separately if needed.
- **Due soon:** invoices with known due dates in a named interval and unresolved handoff/review work. If paid status is not authoritative, label them “upcoming due dates; payment status unavailable.” Never infer unpaid from a due date alone.
- **Period completeness:** expected obligations, received obligations and verified obligations. Inactive/excluded and not-yet-expected accounts must be explained. Bill count and account count are not interchangeable.

### 3. Prioritized work queue

Each row shows issue, property/account, vendor, bill period, due/expected date, supported amount (or “unknown”), owner and next action. Rank confirmed late/urgent items, failed imports, overdue expected bills, material anomalies and routine review using an explicit rule. Filters and summary drill-downs preserve the same scope.

Opening a row reveals source evidence and the rule that triggered it. Missing bill → collection history and receipt matching. Suspected duplicate → side-by-side source comparison. Variance → amount/usage/rate/day comparison. Extraction issue → PDF and uncertain fields. Assignment, acknowledgement and resolution are separate recorded actions. Approval/payment actions are conditional on R03/R04 in the review checklist.

### 4. Financial context

Show received charges for the period with coverage and estimates labels. Separately show any modeled unbilled accrual; do not present it as invoiced spend. Comparisons should use equivalent coverage and a documented date basis. Surface potential overcharges as unverified exposure until a correction or credit supports an actual recovery figure.

### 5. Collection and close status

Group accounts/properties into expected, received, under review and verified states. Display last successful connector/import activity and failed jobs. A monthly close checklist should require resolved exceptions or explicit documented overrides; “verified” must not imply paid, approved or financially closed.

Maps, tree-equivalent emissions, and raw property/account counts belong in secondary portfolio/sustainability views. They can remain available without taking the most valuable dashboard space.

## Proposed dashboard data contract

`DashboardSummary` carries scope, period basis, as-of time, coverage denominator, received/verified/missing counts and received charges. `WorkItem` carries source invoice or expected bill, rule/version, evidence, owner, priority, state and permitted actions. `IngestionStatus` carries connection/job, last successful run and freshness. Every record and download rechecks authorization server-side.

Maintain separate invoice review, collection and payable states. Suggested review lifecycle: received → extracted → validated → review required / verified, with superseded/void states. These are proposed new-product states, not claims about Tango. Store source statement amounts without conflating new charges, balance due, credits and payment status.

Cache or precompute portfolio aggregates as needed, keying them by organization, scope and calculation version. Invalidate on accepted imports, corrections and relevant settings changes; expose summary freshness. Background job completion must not bypass the user's current access rights.

## Concept data and validation

The inline dashboard is fictional and local: four example properties, 24 expected statements, 21 received, 18 verified, three received bills needing review, three missing statements, and $42,860 in received charges. It demonstrates two layouts and local drill-downs; it is not connected to Tango or a Laravel backend. Review findings overlap due-date reminders; those counts must not be added as unique bills.

Before implementation: resolve R01–R11, choose the operator-first or period-close default, validate the expected-bill model with representative records, and implement one complete slice from intake through exception review to reconciled dashboard. Success means each number has a denominator, evidence and a useful next action.
