# IOMAD 5.1 Operational Gap Assessment

This assessment applies to pinned IOMAD commit
`55b3128b8058d27f6cc4320850ca709ed5a792a9`. It distinguishes extension work
from functionality already present in IOMAD 5.1. Run
`make operational-baseline` whenever the pin changes.

## Assessment

| Area | Pinned 5.1 baseline | Decision |
|---|---|---|
| HR provisioning | `auth_iomadoidc` is company-aware and supports field mapping; institution packs provide controlled bulk provisioning | Interactive OIDC is not an authoritative lifecycle feed. Build a separate, resumable connector before claiming HR synchronization |
| Tenant payments | Companies reference core Moodle payment accounts through `paymentaccountid` | Do not add gateway-secret columns to company tables. Configure reviewed gateways through the payment subsystem |
| Database performance | Company shortnames are uniquely indexed; IOMAD tracks already have user/company and company/course indexes | Reject the proposed DDL. Measure production plans before adding an XMLDB-managed index |
| Query security | Moodle contexts, capabilities, parameterized DML, IOMAD company scope, and isolation audits form the boundary | Never intercept global DML or append inferred company predicates to arbitrary SQL |
| Recertification | IOMAD provides expiry templates, repeat settings, company SMTP configuration, and scheduled expiry tasks | Use the native model. This repository carries a checksum-guarded fix for a pinned task regression |

## Authoritative HR Synchronization

OIDC authenticates a user; it does not by itself reconcile joins, moves,
departures, departments, manager relationships, licenses, roles, or course
assignments. A production Entra ID, Okta, or SCIM connector must:

1. Poll a provider delta API from a scheduled task using a least-privilege
   service identity.
2. Map immutable issuer, provider-tenant, and provider-object IDs to an
   approved IOMAD company code. Display names, domains, and profile fields are
   not authorization inputs.
3. Deny unknown company mappings and never create a company from a login claim.
4. Normalize input into a signed, checksummed manifest and reuse the
   institution-pack `doctor`, `validate`, `plan`, `dry-run`, `apply`, `resume`,
   and `report` controls.
5. Use IOMAD and Moodle APIs for user, company, department, role, license, and
   enrolment changes.
6. Apply a reviewed suspension grace period, protect privileged users, and
   keep pseudonymized audit records without claims, tokens, or personal data.
7. Store provider credentials and per-company delta cursors in protected
   secret/state stores and make every operation replay-safe.

Keep the identity-provider login configuration and authoritative workforce
lifecycle configuration as separate security domains.

## Tenant Payment Accounts

Do not add Stripe or other provider secrets to
`local_iomad_companies`. The pinned schema already links each company to a core
Moodle payment account.

- Admit only a gateway release reviewed for IOMAD/Moodle 5.1.
- Store credentials through the gateway configuration boundary and production
  secret store, not custom company columns, source control, logs, or data packs.
- Resolve the company and payment account server-side from the authenticated
  order and capability context.
- Fail closed when a company has no approved account. A global-account fallback
  requires an explicit, documented merchant-of-record policy.
- Verify signed webhooks and bind provider account, amount, currency, company,
  course, order, and idempotency key before changing enrolment state.
- Never trust browser-submitted company, account, price, or course ownership.

## Performance Process

The proposed SQL targets `local_iomad_track`, but the pinned table is
`local_iomad_tracks`. Its useful composite indexes already include
`(userid, companyid)` and `(companyid, courseid)`; company `shortname` is
already unique and company `id` is the primary key.

For a real performance change:

1. Capture a representative slow query and tenant-safe fixture.
2. Record `EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)` and
   `pg_stat_statements` evidence from a non-production clone.
3. Confirm query selectivity, ordering, write amplification, table size, and
   existing indexes.
4. Add any justified index through the owning plugin's XMLDB upgrade API.
5. Test clean install, upgrade, rollback by recovery-set restore, and query-plan
   improvement on PostgreSQL 15 and the production major version.

Do not run unmanaged `CREATE INDEX` statements against a live IOMAD database.

## Query Security

Never monkey-patch `$DB->get_records_sql()` or auto-append company predicates.
An interceptor cannot safely infer shared-course, parent-company, delegated
manager, site-administrator, report, or system-task semantics. It also cannot
reliably rewrite subqueries, unions, common table expressions, or aliases.

Each query must start from an explicitly authorized company/department set,
check the required context capabilities, bind all parameters, and return only
records inside that set. The project isolation audit is read-only and verifies
the resulting relationships independently.

CVE-2026-1517 is reported for IOMAD versions through 5.0. The pinned 5.1
baseline is outside that stated affected range, but source review, dependency
scans, capability tests, and cross-company leakage tests remain mandatory.

## Recertification

Use the native course-expiry warning, manager digest, license-expiry, email
template, repeat-period, and company SMTP settings before introducing another
worker.

The pinned `course_expiry_warning_task.php` contained a hard-coded company ID,
selected the wrong template name, referenced unresolved user/course records,
and used a misspelled supervisor-disable field. The tracked override fixes
those defects without changing the documented warning-time unenrolment
semantics. Its manifest records SHA-256
`1fb2808233776e25360e9b31ee58ddeb6252049ccbdc360fad45119ca0e13735`
for the unmodified upstream file.

Execute the task in a controlled environment with:

```bash
docker compose exec -T iomad php admin/cli/scheduled_task.php \
  --execute='\local_iomad\task\course_expiry_warning_task'
```

Acceptance must cover parent and child companies, company course overrides,
disabled templates, user and supervisor recipients, custom SMTP, repeat
periods, expired enrolment behavior, duplicate suppression, and Mailpit
delivery without cross-company recipients.

## Reviewed References

Reviewed on 2026-07-25:

- IOMAD 5.1 pinned source and release history
- IOMAD administration and company settings
- Moodle payment subsystem and DML APIs
- NVD CVE-2026-1517 and the linked IOMAD advisory

Generic hosting, theme, or consultancy articles are not compatibility or
security evidence.
