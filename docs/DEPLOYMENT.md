# Deployment target: Coolify

User decision: deploy VIA Bill Management through Coolify, following the operational approach of the other project. This records the target; no Coolify resource or production deployment has been created. The other project's exact configuration has not yet been inspected.

## Planned shape

- Build a production Docker image with Composer dependencies and compiled Vite assets. Use a production HTTP server; the current Dockerfile and Compose configuration are development-only, rely on a source bind mount, and run Artisan's development server.
- Run the web app, queue worker and a single scheduler from the same application version, with process health checks and restart behavior.
- Provision a dedicated PostgreSQL database and credentials for this application. Keep business records in ordinary tables; defer Timescale until interval ingestion is implemented and benchmarked.
- Configure secrets at runtime in Coolify, including a stable application key. Keep credentials, database access and grants separate from Contract Manager.
- Persist private bill documents outside replaceable containers, using private object storage or an explicitly configured persistent volume. Define and test database/document backups and recovery together.
- Configure transactional email for customer verification, password recovery and invitations. Customer onboarding and the staff management area require separate authorization checks. Microsoft SSO is not a launch prerequisite.
- Configure domain, HTTPS, trusted proxies, secure cookies, production logging and health checks. Run controlled migrations once per release, without demo seeding, and document rollback compatibility.

## Release boundary

Keep `APP_ENV=production`, `APP_DEBUG=false` and `DEMO_ENABLED=false` for deployments. Existing demo routes intentionally return 404 outside local/testing; do not change the environment to local to expose the prototype. Implement authentication and organization/property authorization with tests before real data. A deployable authenticated application is still a future increment.

When deployment work begins, inspect the other project's non-secret configuration to align conventions, then confirm the target Coolify server, repository/branch and domain. Reuse the operational pattern, not its secrets, databases or role grants.

## References

Reviewed September 29, 2026:

- [Coolify deployment methods](https://coolify.io/docs/core/what-is-coolify)
- [Build and deployment model](https://coolify.io/docs/core/build-deployment-model)
- [Persistent storage](https://coolify.io/docs/applications/configuration/persistent-storage)
