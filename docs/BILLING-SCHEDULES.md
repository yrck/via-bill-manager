# Billing schedules and collection coverage

Implemented September 30, 2026. From a customer location, open **Billing schedule** beside a utility account. Only the organization owner with access to that property can change the schedule; other authorized members can read it. No pilot account schedules are inferred or enabled automatically.

## Receipt rules

A monthly rule specifies its effective reporting month, receipt day (1–31), same/following receipt month, and grace period (0–30 days). Short months clamp the receipt day to month end. An expectation becomes missing on the day **after** receipt date plus grace, using UTC. Receipt expectations are separate from statement issue, service and payment-due dates.

**No bill expected** stops expectations from the chosen reporting month. Add another monthly rule to resume. Each change records actor, reason and time; stale forms are rejected. The latest change for a particular effective month wins until the next effective rule. Earlier months keep their earlier rule unless explicitly backdated. Entry permits up to 24 months back or 12 months ahead.

Generated expectations are unique by account/reporting month. Applying a schedule to an already received month reuses its expectation. Stopping a schedule excludes an unreceived expectation from collection queues but preserves it and any received statement. Statement versions, PDFs, amounts and review history are never deleted by schedule changes.

## Dashboard and register

The selected reporting month shows missing/awaiting counts and received-versus-scheduled coverage. Unconfigured accounts are counted separately as unknown; explicitly stopped accounts are excluded. Missing and review queues show the oldest five items across all months. Links open the matching register filters. A late-arriving bill clears its missing state and enters review; corrections continue to count only once.

The latest recorded receipt date is shown, not an assertion that a provider connection is healthy. If monthly expectations have not been generated for the selected month, coverage is marked pending refresh and missing/awaiting headline totals are withheld. Reads do not generate records. Customer-view mode uses the selected member's current grants and remains read-only.

## Scheduler operation

Saving a rule materializes expectations through next month. The default local and Coolify scheduler refreshes them daily at 01:10 UTC. The command is repeatable, skips suspended organizations and operates under the same organization lock as schedule/intake changes. Keep one scheduler process. For immediate catch-up:

```sh
# Local
docker compose exec app php artisan billing:refresh-schedules
# Coolify app container
php artisan billing:refresh-schedules
```

Reload the Compose definition and redeploy for the now-default scheduler. Locally, after migrations, run `docker compose up -d scheduler`. A queue worker is not required by this command. No emails or provider requests are sent.

## Remaining scope

This is monthly scheduling, not a full audit/exception system. Irregular cycles, separate supplier expectations within one billing account, assigned cases, due-soon/payment reconciliation and automated provider freshness are still planned. Receipt scheduling supports account inventory, while manual statement intake still supports only USD electricity.

Tests cover grace boundaries, leap-year/next-month receipt dates, closure/resumption, late receipts, duplicate refreshes, stale edits, foreign/revoked access and suspended organizations. The original account-review uncertainties remain open pending real workflow evidence.
