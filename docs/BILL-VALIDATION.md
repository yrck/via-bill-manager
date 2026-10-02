# Explainable bill validation

Implemented October 1, 2026. These are provisional screening rules for monthly USD electricity bills, not certified audits, tariff checks, weather normalization or proof of overbilling. Pilot review should confirm thresholds and how providers print service-period boundaries.

## Rules version: electricity-monthly-v1

- **Service duration:** inclusive start/end days; flag fewer than 20 or more than 40. Missing or reversed dates and unsupported commodity/currency yield insufficient data.
- **Service continuity:** compare the immediately preceding received statement only when its reporting month is exactly one month earlier. Consecutive inclusive periods are clear. Gaps, overlaps (including shared endpoints) or nonprogressing service dates require investigation. Missing adjacent history is insufficient; the system does not infer missing service from it. This first rule does not search all historical intervals for nonadjacent overlaps.
- **Usage per day:** current kWh/inclusive days against prior kWh/inclusive days. Flag an absolute change greater than 30%, in either direction.
- **Current charges per day:** current charges/inclusive days against prior current charges/inclusive days. Same threshold. Prior balances and amount due are excluded. This is not a unit-price or rate-compliance test.

Daily comparisons require adjacent reporting months, valid dates, matching USD currency, a strictly positive baseline and nonnegative current values. Missing values, zero baselines and credits yield insufficient data. Rules explain their inputs, units, threshold and limitations. No weather, occupancy or seasonal adjustments are applied. Duplicate PDF/invoice/month and balance reconciliation remain blocking intake checks, separate from these warnings.

## Execution and evidence

Intake and corrections evaluate current statements for the same utility account inside the organization-locked transaction. Recomputing the account also updates later comparisons after a baseline correction or out-of-order arrival. Only changed input fingerprints create new evaluations; fingerprints include the rules version and both source revisions. Repeated refreshes do not create duplicate evaluations or investigations.

Regular PostgreSQL `bill_validation_runs` rows retain source and comparison IDs/revisions, raw dates/usage/charges, outcomes, rule version and UTC creation time. Statements point to their current evaluation. Prior evaluations are retained; the application has no mutation/deletion endpoint for them. There are at most four outcomes per evaluation. Every bill detail includes its version's evaluation history and links to the comparison version. History is paginated.

Existing bills initially show Not evaluated. An authorized reviewer can use Refresh validation on a current received bill to evaluate the account. GET requests do not write evaluations. The migration creates tables and nullable references only, and does not automatically audit private pilot data. New intake/corrections evaluate automatically; no worker or scheduler is required for this first synchronous implementation. Account-level work grows with its statement history; move to bounded jobs with execution-time authorization before large-scale historical ingestion.

## Investigations and access

The bill's investigation link lets a reviewer coordinate follow-up against these findings. Each investigation event now captures the current evaluation ID as well as the statement revision. New comparison evidence flags the investigation as changed even when its own bill revision is unchanged. Stale forms cannot resolve against an outdated evaluation. Decisions and bill verification remain unchanged until a reviewer acts. No cases or notifications are generated automatically.

Readers use the existing organization and property boundary; comparison bills belong to the same utility account/property. Refresh requires active, verified customer access and reviewer property grants. Viewers and manager customer previews are read-only. Historical statement pages never offer refresh controls.

Still planned: configurable/customer-approved thresholds, seasonal and broader interval comparisons, more named rules, multi-finding case relationships, severity, automatic notifications, and a cross-account findings dashboard. B03 remains partially implemented. Legacy uncertainties remain in ACCOUNT-REVIEW-CHECKLIST.md.
