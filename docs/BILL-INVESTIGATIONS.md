# Bill investigations

Implemented October 1, 2026. This is manual coordination, not an automated audit or provider collection system.

From a received or missing bill, a property-authorized reviewer can open one investigation thread for that utility account and billing month. The reviewer supplies an investigation note, next action and follow-up date. Assignment is optional; assigned users must be active, verified reviewers in the same organization with access to the property. Owners without reviewer/property access cannot bypass that boundary.

Open investigations support notes, assignment/follow-up changes and resolution. Resolved investigations can be reopened with a note and a new follow-up. Resolution preserves the last assignment and action for reference. Every write appends an event containing the actor, UTC time, note, source revision and before/after state. There are no edit/delete endpoints for history. A unique bill key and version checks reject duplicate or stale submissions.

Bill verification and investigation status are independent. Resolution is not proof of payment, a refund, or recovered savings. A statement arriving or being corrected displays a source-change warning, including for resolved investigations; it does not silently reopen or close work. Saving a subsequent action records the current source revision, and a change since the form was loaded rejects the submission. Source revision zero means no statement had been received.

The workspace and register link to Investigations & follow-ups. The queue shows only accessible properties, ordered by follow-up date, and filters open/resolved, assigned to me/unassigned, and overdue open work. Overdue begins the UTC day after the follow-up date. Schedule exclusions preserve investigations and their bill-detail access. Revoked assignments remain historical and show as no longer eligible on the bill page; a current reviewer can reassign or unassign. Assignment never grants property access.

Viewers and staff customer previews can read scoped investigations without write controls. Staff preview uses the selected customer's grants and identity for the “assigned to” filter while retaining the manager's session. Preview sessions cannot submit investigation writes.

The migration creates regular PostgreSQL tables only. It creates no cases and changes no existing statements, schedules or pilot data. Tests use in-memory SQLite.

Still planned in B03: rule versions and comparison evidence, explicit insufficient-history outcomes, automatic reevaluation, severity, multiple related findings, notifications/escalation and delivery tracking. Recovery accounting is separate B06 work. This manual workflow does not complete B03 or settle the legacy questions in ACCOUNT-REVIEW-CHECKLIST.md.
