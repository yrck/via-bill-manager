# Statement corrections and detailed charges

Implemented September 30, 2026. Open an authenticated customer bill and choose **Correct statement**. Reviewers with property access can correct entered values, optionally attach a replacement PDF, and give a reason. Saving creates a new version in Needs review. Account and reporting month remain fixed in this increment.

## Evidence and calculations

- `statements` remains the current projection, one per expected bill. Register/dashboard counts and totals include only the latest values.
- `statement_revisions` retains financial/source snapshots, document references, reasons, actors and timestamps. Historical views are read-only and marked Superseded. Application workflows never update these snapshots.
- `bill_documents` retains original files and hashes. A correction can reuse its existing PDF or add a replacement; a PDF belonging to another statement is rejected.
- `statement_line_items` belongs to a particular revision. Printed descriptions, categories, quantities/units, rates/rate units, source references and integer-cent amounts are retained. If lines are entered, their sum must equal current charges, excluding prior balance. No detail means not recorded, not zero. Printed amounts are authoritative; quantity × rate is not silently substituted.
- Review decisions identify their financial version. A correction increments the concurrency version and resets review, preventing stale browsers from verifying or overwriting it. Previous decisions do not verify the new version.

The balance check remains current charges + balance forward = printed amount due. Verification does not establish approval or payment status. More complex payment/adjustment breakdowns need additional samples and modeling.

## Access and migration

Current and historical reads/downloads use the same organization/property checks. Viewers can read, reviewers can correct, and manager customer-view remains read-only with fresh manager/member/grant checks. Downloads remain private and non-cacheable. Writes serialize with organization suspension and membership changes.

Migration `2026_09_30_110000_add_statement_revisions.php` preserves existing bills/PDFs as version 1 without changing current values or review status. Its system timestamp describes introduction of history, not original upload time. Existing review events are assigned version 1.

Back up PostgreSQL and private documents before deployment. Once corrections exist, rollback is refused because the previous one-document schema cannot retain all evidence. Use a reviewed forward repair or restore the complete pre-migration backup; do not delete history to force a downgrade.

## Remaining B01 work

Voiding, multiple independent bills per account/month, split delivery/supply reconciliation, account/month reassignment and explicit payment components remain planned. Intake is USD electricity only. Existing bills are not automatically enriched with line items. No real pilot bill was corrected during implementation.

Tests cover original/revised values and PDFs, charge totals, review invalidation, stale submissions, duplicate historical invoice numbers, authorization/revocation, manager read-only access and migration preservation. Tests use in-memory SQLite. The migration was also applied to local PostgreSQL after a private backup; a fictional rendered form was used for browser checks.
