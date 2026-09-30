# Private test-account provisioning

Use this only for explicitly authorized internal pilot data. This is a trusted-console operation, not a public API, migration seeder or anonymous demo fixture. Do not commit source statements, identifiers or manifests.

```sh
# Local container; the private file must exist under the mounted project.
docker compose exec app php artisan account:import-test storage/app/private/setup/pilot.json --owner=owner@example.test --create-owner

# Coolify app container terminal, after deploying the code and migrations:
php artisan account:import-test /tmp/pilot.json --owner=owner@example.test --create-owner --allow-development-server
```

Transfer the manifest privately or paste it into a mode-0600 temporary file in the app container. `-` reads the JSON from standard input. JSON numeric identifiers are rejected: account references, ESI IDs and meter numbers must be strings. The manifest includes `dataset_key`, `account_name`, `legal_name`, `billing_address`, `location_name`, `service_address`, and `points`; each point includes `esi_id`, `label`, `service_address`, `reference`, `supplier`, `meter_number`, `observed_on`, `source_document`, `source_sha256`. Validation rules in TestAccountImport are the contract. Source hashes are supplied extraction provenance, not an automatic server-side verification of PDFs.

The importer creates one explicitly marked test organization, owner membership/grant, one location and linked utility accounts/service points/meters atomically. Existing dataset keys with identical data/owner do nothing; changed payloads or owners fail. It never restores revoked access on rerun. New dataset keys deliberately create separate test accounts; do not change the key to repair an existing import. Concurrent duplicate-key imports are protected by the database uniqueness constraint and transaction; retry the same file if a concurrent import wins.

`--create-owner` creates a missing active login with an unknown random password and no verified email or platform-staff role. The person must use password recovery and complete email verification before accessing the data. Existing passwords, verification and memberships are unchanged; inactive users are rejected. Configure SMTP for delivered verification/recovery/invitation messages. Local development can use expiring links saved privately by the trusted operator. No login should be activated by committing credentials or bypassing route verification.

Service points are unique per organization and ESI ID. Physical meters and billing accounts are separate entities with explicit links. Initial meter observations do not assert effective replacement dates or authorize external data retrieval. Meter reads on location pages use the existing organization/property access boundary. Grants remain at location level; separate building permissions require separate locations or a future finer-grained design. No invoices, expected bills, interval readings or provider connections are created by this command.

Tests use fictional identifiers and in-memory SQLite. The private pilot is absent from Git, anonymous demo queries, and image build contexts. Real customer data must remain behind authenticated account/property authorization on both local and development deployments.
