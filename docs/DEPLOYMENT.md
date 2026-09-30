# Coolify development-server deployment

The Git deployment configuration is `/compose.coolify.yaml`, matching VIA Contract Manager's filename and nginx/PHP-FPM/PostgreSQL pattern. This application builds its own Composer dependencies and Vite assets. It uses its own credentials and volumes. The local `compose.yaml` and `Dockerfile` remain the Mac development setup.

## Coolify configuration

Create a **Git-based Application**, using the GitHub integration or a deploy key:

| Setting | Value |
| --- | --- |
| Repository | `https://github.com/yrck/via-bill-manager` |
| Branch | `main` |
| Build Pack | Docker Compose |
| Base Directory | `/` |
| Docker Compose Location | `/compose.coolify.yaml` |
| Domain | Assign your HTTPS hostname to `web`, internal port **80** |
| Other service domains | Leave blank |

Use the Git-based application rather than pasting the file into a standalone Compose service. Reload the Compose definition after repository changes. Enable automatic deployments in Coolify if pushes to main should redeploy. Pushing this file does not create a Coolify resource or configure its webhook.

### Runtime variables

Set these in Coolify, not Git or build arguments:

- `APP_KEY`: generate a new stable Laravel key for this application. In a private terminal run `printf 'base64:'; openssl rand -base64 32`. Preserve it across redeployments and include it in recovery planning.
- `APP_URL`: the final HTTPS URL.
- `DB_PASSWORD`: a new dedicated strong password. Defaults for database/user are `via_bills`; optionally set `DB_DATABASE` and `DB_USERNAME` before first deployment. Changing these values does not change credentials in an existing database volume.
- `TRUSTED_PROXIES`: comma-separated IP addresses/CIDRs for the Coolify reverse proxy network. Determine these on the target server; do not use `*`. Forwarded scheme, port and client IP are trusted only from those addresses.
- Email: defaults to `MAIL_MAILER=log` for server setup, which does **not** deliver email. Before customer onboarding, set `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and a valid `MAIL_FROM_ADDRESS`. Use the SMTP provider's scheme/port settings. Verification, password recovery and invitations require working delivery.

Even on a development server, this stack fixes `APP_ENV=production`, `APP_DEBUG=false`, secure/encrypted database sessions, and `DEMO_ENABLED=false`. The local fictional dashboard/portfolio/bill routes remain unavailable. Visiting `/` redirects to `/workspace`, which sends guests to sign-in. Start new customer accounts at `/register`. Do not change the environment to `local` to expose the anonymous demo remotely.

## Startup and optional background processes

The default stack runs `db`, a one-shot `migrate` job, `app` (PHP-FPM) and `web` (nginx). Migrations must succeed before PHP starts. No demo seeder, sample data, user or role grant runs during deployment. The web health check requests Laravel `/up`. This is a liveness check, not a database/mail end-to-end readiness test.

Worker and scheduler definitions are reserved behind the **background** Compose profile. They are not needed by the currently synchronous customer mail and review workflows. Enable that profile through the deployment's Compose invocation when background jobs or schedules are implemented; in a CLI deployment use `docker compose -f compose.coolify.yaml --profile background up -d`. Keep one scheduler instance. The worker stops gracefully with time to finish a job.

Images include dependencies and compiled frontend assets, with no repository bind mount. PostgreSQL and PHP have no published host ports. Only `web` should receive a public domain. Application logs go to stderr; nginx access logs omit URL paths/query strings because invitations and verification URLs contain tokens. Application error logs still require restricted access.

## Persistence and updates

The stack's `postgres-data` and `app-storage` named volumes persist across container replacement. Volume names are scoped by the Coolify resource/project; do not share external volume names with other applications. Keep the resource identity stable and never remove volumes to perform a routine update. Private storage is mounted only into application services, not nginx.

Back up the database, private storage and application key before migrations. Test restoration before relying on the server. Redeploying an old image does not undo migrations: review compatibility and recover from backups when required. A clean deployment starts empty; it does not import the Mac database, uploads or login sessions.

To provision the first manager after they register and verify their nu-devco.com email, use the app container terminal:

```sh
php artisan platform:staff employee@nu-devco.com
```

No secrets, databases or role grants are reused from Contract Manager. Creating a manager requires the existing active verified employee account.

## Verification

Before sharing the server, check HTTPS, `/up`, login/registration, delivery of verification/recovery/invite mail, account isolation, and manager access. `/portfolio` and `/bills` must return 404 because they are local demo routes. Customer bills are under `/workspace/{account}/bills`.

The repository supplies deployment packaging; DNS, TLS, Coolify resource settings, backups and server access still need to be configured on the target host. Manual PDF intake/downloads are available. Automated extraction, document preview, subscriptions and external integrations remain unfinished. PHP allows 10 MiB files / 12 MiB request bodies; the application validates PDFs up to 8 MiB. Rebuild the app image for these limits to take effect.

Reference: [Coolify Docker Compose documentation](https://coolify.io/docs/applications/builds/docker-compose).
