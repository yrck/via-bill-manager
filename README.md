# VIA Bill Management

Laravel 13 + Livewire 4 + Tailwind 4 + PostgreSQL 17, with Docker-based PHP development.

## Product direction

A public customer-facing SaaS application: customer accounts and isolated organization workspaces, plus a separate VIA management suite for customer administration. Initial sign-in will use email/password; Microsoft sign-in can follow later. Customer registration, email/password login, email verification, password recovery and isolated workspace entry are implemented. Account owners can invite multiple teammates with individual logins, viewer/reviewer roles and selected property access. They can also change member roles/property grants or deactivate and restore memberships, with recorded reasons and before/after history. The staff management suite now includes a customer directory, account/team overview, and organization suspension/restoration with recorded reasons and actors.

## Current increment

A working local-only dashboard using fictional September 2026 statements. Property scoping, missing/review/due filters, period-close view and evidence drill-down work. Summary totals and queue entries now query persistent PostgreSQL records. The Portfolio page lists four fictional locations and 24 utility accounts; expected bills are separate from the 21 received statements. The bill register supports property/status filters and account search. Statement detail pages support persistent demo review notes, verification and reopening, with original findings preserved and stale-tab protection. Other navigation entries are visibly planned.

The organization/property authorization foundation now scopes authenticated customer workspace locations and counts. Owners can create locations and utility accounts inside their customer workspace; assigned members can view them. Customer bill detail/review integration, Microsoft SSO, ingestion, payments and a full operational audit trail remain unimplemented. No real data has been loaded. Review history identifies only a local demo operator. The demo requires `DEMO_ENABLED=true` and `APP_ENV=local` (or testing); it returns 404 in other environments. This Compose configuration binds only localhost and uses Artisan's development server. It is not a production deployment.

## Local setup

Use Node 22.12+ (or a compatible newer version) and Docker Desktop. Host PHP is not required.

```sh
cp .env.example .env
# Set a unique local DB_PASSWORD and DEMO_ENABLED=true in .env.
docker compose build
docker compose run --rm --no-deps app composer install
docker compose run --rm --no-deps app php artisan key:generate
npm ci
npm run build
docker compose up -d db app
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

Customer accounts start at http://localhost:8091/register or http://localhost:8091/login. Local verification/recovery emails use the log mailer; outbound delivery must be configured before deployment. Direct registration asks for an account name and creates a new empty customer account. Verified customers can open it from My accounts. The header switcher lists active account memberships and the current account; each account has its own URL so separate tabs can stay on different accounts. Owners can use “Add location” to create a property, then record utility account numbers, providers and utility types. Only the owner receives a grant automatically; existing teammates need explicit access. Owners can use “Manage team & invitations” inside their workspace to invite teammates. Invite registration creates only a login, then joins the existing organization after email verification and explicit acceptance.

Open http://localhost:8091 or the bill register at http://localhost:8091/bills. The demo uses file sessions/cache. Run the guarded, repeatable seeder to populate fictional business records; rerunning it preserves existing records and edits. PostgreSQL has its own project volume and no host-exposed database port. `docker compose --profile background up -d worker scheduler` starts optional workers after migrations. `docker compose down` stops services without deleting the database volume.

```sh
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
npm run build
```

The base test case forces in-memory SQLite before database refresh and removes application connections, even when Compose exports PostgreSQL settings. Source changes are mounted into the container; rebuild frontend assets after CSS/JS changes.

## Next slices

1. Review dashboard behavior and resolve the core second-account checklist.
2. Extend authenticated customer workspaces with scoped bill detail/review; initial location and utility-account creation is implemented.
3. Extend VIA customer management beyond organization status; owner member access management is implemented.
4. Refine persisted portfolio/account fields using sample locations and bills; add statement versioning.
5. Implement one intake → validation → exception → evidence → dashboard path.
6. Plan Smart Meter Texas, MISO and PJM connections; add interval storage using TimescaleDB once actual meter workloads are available.

Before the free-versus-paid subscription discussion, the functional core still needs authenticated bill browsing/review with actor history, and a tested intake-to-evidence-to-dashboard workflow. Sample bills and locations will inform intake fields and validation. Subscriptions, pricing and plan limits are intentionally deferred until those workflows are established. Coolify production readiness remains a separate launch requirement.

Planning documents are in [docs](docs/README.md).

The deployment target is **Coolify**, following the other project's operational approach. Production packaging is still planned; the current Docker setup is for local development. See [deployment notes](docs/DEPLOYMENT.md).

## Platform management

The staff suite is at `/management`. It requires an active, verified nu-devco.com account with an explicit manager grant; customer ownership does not qualify. Managers land here after login. Existing managers can grant/remove eligible employee access in “Users & managers” at `/management/users`. The first manager is bootstrapped through the trusted console. An authorized operator can provision an existing account through the trusted container console:

```sh
docker compose exec app php artisan platform:staff staff@nu-devco.com
# Remove platform access:
docker compose exec app php artisan platform:staff staff@nu-devco.com --revoke
```

Replace the placeholder with the intended staff account. No staff grant is created by registration, migrations or the demo seeder. This command does not create a login or verify an email. Managers can enter a recorded read-only customer view from customer details, choosing an active verified member and providing a reason. The view uses that member’s property grants, keeps the manager identity intact, and has a return-to-management button. The management suite shows organization ownership, membership/onboarding status and status-change history. Customer bill access remains subject to explicit organization/property grants.
