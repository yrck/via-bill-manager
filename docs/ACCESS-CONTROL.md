# Organization and property access foundation

Implemented September 29, 2026. Permission infrastructure and initial customer account flows are implemented. The full customer bill workflow and staff management suite are not yet production-ready.

## Identity direction

User clarification, September 29, 2026: Bill Management is a public, customer-facing SaaS product. Customers have their own accounts and organization workspaces. VIA staff have a separate management suite for managing customers. Microsoft sign-in is optional future work, not a launch dependency.

Email/password registration and login, email verification and password recovery are implemented. New organizations currently become usable after owner email verification without staff approval. Owner-issued team invitations are implemented. Public account creation must not grant access to another customer's organization or to platform administration.

Use one Laravel application initially with separately authorized customer and platform-management areas. A user identity and a customer organization are separate records: an organization may contain multiple users. Organization ownership and administration need explicit permissions beyond the existing viewer/reviewer roles. Platform-staff access must use separately assigned permissions; being a customer owner must never imply platform access.

Proposed customer workspace: locations, utility accounts, bills, review work, team invitations and organization settings. Proposed staff suite: customer directory, onboarding/activation/suspension, membership support and operational status. Any staff access to customer bills must be explicit, scoped and recorded. Impersonation, subscription charging and payment processing are not assumed requirements.

The existing organization/property query boundary remains useful. Registration records an organization owner and an active reviewer membership. Owner invitation management is implemented; member editing/removal and the staff suite remain pending; inactive users, memberships and organizations are rejected on workspace access. Do not reuse Contract Manager's credentials, grants or internal-only identity assumptions.

## Implemented rules

- Database defaults keep users and memberships inactive. The customer registration service explicitly activates the new user and their new organization membership; workspace entry additionally requires email verification.
- Every portfolio may belong to one organization. Unowned portfolios are never returned by authenticated access queries. Existing fictional data remains unowned.
- Membership has a viewer or reviewer role. Neither grants all properties automatically. Every allowed location requires a separate grant.
- Viewers can read granted locations, accounts, expected obligations and statements. Reviewers have that same visibility plus statement-review eligibility. Neither role implies approval, payment, grant administration or import rights.
- A missing bill is visible as an obligation but cannot be reviewed until a statement exists.
- Access checks read current user/membership/grant state from the database. Revocation, role changes and portfolio ownership changes take effect on the next query, including when the caller holds an older User instance.
- Malformed cross-organization grants do not confer access. The membership organization must equal the portfolio organization and the explicit organization context supplied to the query.
- Anonymous demo queries require the fixed fictional portfolio key AND no organization owner. Assigning an organization removes that portfolio from demo reads and writes.

## Integration surface

`app/Access/BillingAccess.php` provides scoped query builders for locations, utility accounts, expected bills and statements. Scope aggregates and exports through these builders before calculating totals. Registered Laravel gates `view-bill` and `review-bill` accept an explicit organization ID and expected-bill ID. Anonymous callers are denied.

Authenticated workspace routes use the scoped query builders for locations and counts. The bill gates are not yet connected to customer bill detail/review routes. Current Livewire pages and review actions remain the separately guarded local fictional demo. Do not use its `App\Demo\ReviewStatement` service for real users or records.

Authenticated review integration must recheck permission at action time and atomically write the decision with the authenticated actor. It must define revocation/concurrent-write behavior, audit retention and record-level validation. Downloads, imports, jobs, caches and totals must use the same organization/property boundary. Grant management and organization-wide roles are not implemented; the only grants created in this increment are isolated test fixtures.

## Tests and remaining work

Tests cover property-scoped totals, viewer/reviewer distinctions, inactive/unprovisioned identities, anonymous access, cross-organization and unowned portfolios, explicit membership in multiple organizations, missing bills, revocation using stale instances, role downgrades, property moves and the anonymous-demo ownership boundary. The suite uses forced in-memory SQLite. PostgreSQL migrations are applied locally; concurrent production authorization behavior is not yet validated.

Next: customer email/password authentication and verification, safe organization onboarding, owner/admin permissions and invitations, authenticated customer routes, then the separately authorized staff suite. Add Microsoft account linking later with explicit identity-linking rules. No real data until that complete path is implemented and tested. R08 in ACCOUNT-REVIEW-CHECKLIST.md remains open; these are proposed new-product permissions, not a claim about legacy behavior.

## Customer account increment

Routes: `/register`, `/login`, `/forgot-password`, `/reset-password/{token}`, `/verify-email`, `/workspace`, and `/workspace/{organization}`. POST routes handle registration, login, logout, verification resend and recovery. Authentication uses Laravel's session guard and password broker, signed verification links, hashed passwords, session regeneration, CSRF protection and request throttles. Recovery responds generically for known and unknown emails. Password reset tokens are single-use. Password-session checks invalidate older authenticated workspace sessions after a password change.

Registration lowercases email and atomically creates a user, new UUID-keyed organization, owner reference and reviewer membership. Client-supplied roles, ownership, verification status and organization IDs are not used. Ownership does not confer platform access or bypass location grants. No locations or bill data are copied into the new organization. No platform permission can be selected during registration.

Current activation choice: active account/organization membership on registration, with verification required before workspace entry. Staff approval is not part of this increment. Organization suspension is enforced by scoped queries and workspace routes; the management UI to change that state remains planned. No grants or real users are provisioned by migrations or tests.

The local demo middleware now applies only to demo routes and is explicitly registered as persistent middleware for Livewire updates. Customer account routes work with the demo disabled, while the anonymous demo remains inaccessible outside local/testing. A raw Livewire update regression test covers disabling the demo after page load.

Mail is logged locally. Configure and test a transactional mail provider, delivery/error handling, secure production sessions/proxies and release packaging before public launch. Public landing content, privacy/terms content, invitation/admin workflows, staff controls and authenticated bill actions remain future work. Continue using fictional data until those record-level paths are tested.

## Multiple users and invitations

A customer account is an organization, not a shared user login. Owners can access `/workspace/{organization}/team` to list members and pending invites, send an invitation and cancel an unused invitation. Each teammate has their own verified login. Existing users can join multiple organizations without losing their previous memberships. Invite registration does not create a second organization.

Invitations specify viewer or reviewer plus explicit location grants limited to the owner's current accessible properties. No selection grants organization membership but no bill access. Owners cannot invite someone as an owner or platform staff member. Other team members cannot administer invitations. Editing/removing accepted members or transferring ownership is not implemented yet.

Tokens are random, stored only as SHA-256 hashes, expire in seven days and are consumed transactionally. The invited address is normalized and immutable in invite registration. Acceptance requires that exact verified, active email; current organization/owner activity and property scope are rechecked. Accepted, expired, revoked and stale-scope invitations fail. An invitation never reactivates or alters an existing membership. Invitation acceptance and cancellation lock the invitation row; issuance locks the organization when checking for duplicate pending invites. Mail delivery failure revokes the unusable invite and presents a retry message. Local invitation emails use the log mailer; tests fake all notification delivery.

Tests cover owner-only management, team-page rendering, invited registration/verification, selected grants and roles, multi-organization membership, wrong addresses, guests, expired/revoked/single-use links, duplicates, inactive existing memberships and changed property access. Customer UI routes remain separate from the fictional dashboard demo. No real invitations were sent during development.

## Platform management increment

Implemented `/management/customers` with database-filtered search/status and pagination, plus customer details showing owner verification, team membership status, pending invitation count and organization access history. `is_platform_staff` defaults false and is not mass-assignable. The `manage-customers` gate reads current active/verified/staff flags on every request; customer ownership is insufficient. There is no registration field, domain rule, first-user shortcut or public endpoint for granting staff privileges.

Trusted console provisioning: `php artisan platform:staff <existing-email>` requires an existing active, verified account; `--revoke` removes the grant. Console changes are logged with the user ID. No local account was promoted during implementation. Staff permissions grant management metadata and organization status controls, not customer bill/property access or impersonation.

Suspension/restoration requires a 10–2,000 character reason. Changes atomically update the organization and append actor ID/name, old/new state, version, reason and UTC time. A row lock and submitted status version prevent stale overwrites and duplicate transitions. Records have no edit/delete UI. This limited history is not a full tamper-resistant operational audit system.

Suspension blocks organization workspace, team/invitation and scoped billing access. It preserves users, memberships, grants and records; users can still use unrelated organizations. Restoration honors existing active user/membership/grant rules and does not reactivate individually disabled accounts. A previously issued invitation may work after restoration if otherwise valid and unexpired.

Tests cover guest/customer denial, explicit provisioning, unverified/inactive/revoked staff, metadata filtering, separation from customer bill access, recorded suspension/restoration, organization isolation and stale/invalid status transitions. MFA for staff, granular support roles, member editing/removal, customer communications and authenticated bill workflows remain future work before production rollout.

## Employee managers and customer view

User-requested update: manager designation is now available at `/management/users`. Only existing managers can grant/remove it. Granting requires an active, verified address at exactly `nu-devco.com`; subdomains and look-alike suffixes do not qualify. Eligibility is also enforced on every management request and by the bootstrap console command. Customer signup is still open to other email domains. No signup automatically becomes a manager. Self-modification is blocked in the UI/action; the trusted console remains the recovery path. UI grant/revoke records contain actor, target, result and timestamp, and reject stale expected-role state.

Managers go to `/management` after login, and `/workspace` redirects them there. Public customers retain their existing workspace home.

Customer details offer “Enter customer view”: select a currently active, verified, non-manager organization member and supply an access reason. The view reuses customer workspace/team templates with the selected member's exact property grants and owner visibility. It is read-only and does not replace the authenticated manager or create a customer session. Write forms are not exposed, and direct customer writes still authorize the real manager rather than the viewed member. This is a viewing capability, not full account impersonation or a general billing permission bypass.

Customer views last at most one hour. Manager permission, target account, membership and organization status are rechecked on each view. A persistent banner identifies the selected member and offers Return to management. Start and explicit end are recorded with manager, target organization/user, reason and timestamps. Logout and starting another view close the current record. Abandoned/expired sessions may have no explicit end timestamp and are unusable after the one-hour limit. Customer owners can expose their team page in this view, but invitation actions are hidden. Non-owners cannot view the team page.

Existing demo routes remain local-only and separate. Only implemented authenticated customer pages are covered; bill detail/review parity will expand when those authenticated routes exist. Manager activity records are a support-access history, not a completed tamper-resistant audit system. No actual users were granted manager privileges during implementation.

## Customer account switching (implemented September 29, 2026)

Direct registration asks for an account name and creates that account with an owner membership. Invitation registration creates only a login; acceptance joins the inviting account. Existing users can accept invitations to additional accounts without losing existing memberships.

The customer header now includes an account switcher with the current account, membership role, and links to other active accounts. The account list and workspace entry share a fresh membership query, requiring an active verified user, active organization, and active viewer/reviewer membership. Owner status does not bypass property grants. Suspended organizations and revoked memberships disappear on the next request.

Account context stays in the URL, including team pages, rather than a mutable session-wide selection. Two browser tabs can safely show different accounts. `/workspace` remains the all-accounts chooser; manager home routing and the separate read-only customer-view banner remain intact. Customer-facing signup/navigation uses “account”; the database retains `organizations` to distinguish customer accounts from utility accounts.


## Owner member access management (implemented September 29, 2026)

From Team → Manage access, an active verified account owner can change another member's viewer/reviewer role and property grants or deactivate/restore the membership. Changes only affect this account. Owner access cannot be edited through this feature; ownership transfer is not implemented. Managers viewing a customer see no edit controls and cannot use the customer's identity for writes.

The write locks the organization and membership, rechecks ownership and account status, validates selected properties against the owner's current grants, and atomically replaces grants. Deactivation clears all property grants; restoration requires explicit selection. Every actual change records the actor, reason, version and before/after role, active state, property IDs and names. A stale version fails without mutation. Identical submissions create no history entry. This is scoped access-change history, not a complete operational audit trail.

Tests cover cross-account/member/property boundaries, owner protection, role escalation, deactivation/restoration, membership isolation, history and stale edits.


## Customer location and utility-account setup (implemented September 29, 2026)

Verified active owners can add named locations to an active customer account, then add utility account numbers, providers and utility types at locations they can access. Creation locks the customer organization and rechecks ownership; account/property IDs in submitted data do not select scope. Duplicate location names within a customer account and duplicate utility account references within a location are rejected case-insensitively. Identifiers remain strings, preserving leading zeroes.

A new location receives an explicit owner grant in the same transaction. No teammate inherits a grant automatically. Assigned viewers and reviewers can view location utility accounts, but cannot create them. Revocation and suspension are checked on every request. Customer records remain excluded from the fictional demo. Manager customer-view pages do not expose setup controls or links that would switch away from their read-only context. Location editing, archival, address/meter fields, actual bill intake and external integrations are future increments; no expected bills or statements are fabricated during setup.


## Authenticated customer bill register and review (implemented September 29, 2026)

The customer workspace links to a paginated bill register with property, status and account/provider search filters. Every register/detail query starts with BillingAccess's organization/property scope. Statements use their persisted review status; expected bills without statements are missing only when expected_by is before the application date, otherwise awaiting. No month or fictional as-of date is hard-coded. Source files and intake are not implemented yet, and creating a utility account does not generate expected bills.

Active verified reviewers with an explicit property grant can verify a statement in review or reopen a verified statement. The transaction locks the organization and statement, rechecks current membership/grants, enforces the submitted review version and expected status, and writes an actor-attributed customer_review_decisions event together with the status change. Original findings remain untouched. Missing statements, viewers, revoked grants, suspended accounts and foreign-account records cannot be reviewed. This is review history, not payment authorization or a complete operational audit trail.

Manager customer-view register/detail routes use the selected customer's grants and revalidate the preview session and manager on each request. They expose no write actions, and customer review endpoints continue to authorize the actual authenticated user rather than the previewed user. Customer review history is separate from demo_review_decisions. Tests use fictional records in in-memory SQLite; no business records were loaded into customer accounts.


## Manual statement intake and private PDFs (September 30, 2026)

Verified reviewers with property grants can upload an electricity statement in USD and transcribe its invoice number, reporting month, issue/service/due dates, usage, charges, balance forward and amount due. PDF MIME/signature and 8 MiB size are checked. Monetary input uses exact decimal-to-integer conversion; current charges plus prior balance must equal amount due. New records start in review; original PDFs reside on a private, non-public-serving disk. Downloads recheck current account/property grants and use attachment disposition with no-store and nosniff. Manager preview downloads recheck the manager session and target's grants. No storage URLs or filesystem paths are exposed.

Duplicate PDF hashes within the customer, invoice numbers within the utility account and existing statements for the account/month are rejected. One statement per account/month remains a deliberate initial constraint; correction/rebill versioning is future work. The organization lock serializes scope changes and duplicate checks. Failed transactions remove newly stored files. Intake events record the uploader (or explicitly trusted test-console source) and original PDF hash. Reporting month is entered explicitly, separately from service dates. Intake-created expected records are labeled as not scheduled, rather than asserting a collection deadline. Amount due is historical statement data, not a payment balance/status.

The workspace shows the newest five statements needing review for accessible properties. Verification removes them from that queue; reopening returns them. No unattended OCR, malware scanning, bill payment, or financial approval is claimed. Tests cover monetary reconciliation, duplicate handling, foreign-account IDs, viewers, revocation, suspension, private downloads and the upload-to-review queue path.
