# Security

## Role Mapping

| Business role | IOMAD role | Context | Capabilities |
|---|---|---|---|
| Principal/Registrar | `companymanager` | Company | IOMAD company management capabilities only |
| Trustee/Management | `companyreporter` or parent `companymanager` | Company | Reporting access; parent management only when required |
| IT coordinator | `institutionitcoordinator` | Company | `company_view_all`, `company_user_create`, `company_user_update`, `company_user_upload` when available |
| Teacher/Faculty | `editingteacher` | Course | Course teaching capabilities |
| Student/Learner | `student` | Course | Course participation capabilities |
| Parent/Guardian | `tenantguardian` | Learner user | `moodle/user:viewdetails`, `moodle/user:readuserposts` when available; broad user detail access denied |
| HOD/Dean | `companydepartmentmanager` | Company | Department-scoped IOMAD reporting and administration |

Do not grant site administrator for tenant operations.

`institutionitcoordinator` is a dedicated type-0 company role. It does not
inherit the broad `clientadministrator` or `companymanager` capability set.
Trustee/reporting users use IOMAD manager type 4, and learner-level analytics
are pseudonymized unless the current company context grants
`local/tenantanalytics:viewpii`.

## Data Protection

- No real personal data in committed packs.
- Import logs must not print passwords, tokens, or personal data.
- Demo passwords require `INSTITUTIONPACK_ALLOW_DEMO_PASSWORDS=true`.
- Secret values belong in `.env`, GitHub secrets, AWS Secrets Manager, or SSM, never in Git.

## Vulnerability exceptions

Trivy exceptions must be path-scoped, explain why the finding is not present in
the deployed dependency graph, and include an expiry date. The current
`CVE-2026-4800` exception covers only the official IOMAD CKEditor build
lockfile. The corresponding `node_modules` package is not installed in the
runtime image. Review or remove the exception by 2026-10-31 when updating the
pinned IOMAD release.

The official `auth_iomadsaml2` directory is excluded from release images
because its vendored dependency set has unresolved high and critical
vulnerabilities. It is not suppressed in Trivy. OIDC is the supported
federated-authentication path for this baseline; SAML requires a separately
reviewed implementation and migration plan.

The pinned upstream Composer lock acquired seven advisories in development-only
Symfony tooling after the source commit was published. A tracked, upstream
checksum-guarded lock override updates eleven related packages to patched
releases. CI audits that composed lock and PHPUnit installs it; production
images still use `composer install --no-dev`.

## AI, Commerce And External Integrations

- AI providers are disabled until a tenant has approved data handling,
  credentials, quotas and human review. Do not send passwords, tokens,
  protected learner records or unnecessary personal data to a model provider.
- Payment integrations must use provider-hosted collection, signed webhooks,
  idempotency keys and an auditable order-to-enrolment state machine. Do not
  store card data.
- External synchronization must use stable external IDs, company scope,
  least-privilege service accounts, bounded retries and replay-safe jobs.
- Do not synchronize password hashes or plaintext passwords with WordPress or
  any commerce platform. Use OIDC/OAuth for shared identity.
- Reports, dashboard queries, form submissions, bulk-seat allocation and
  grading views must enforce company/context capabilities before retrieving
  records, not only when rendering output.

## Database Query Security

- Never intercept Moodle's global DML methods or append inferred company
  predicates to arbitrary SQL.
- Derive the authorized company and department set through reviewed IOMAD APIs
  and current context capabilities before querying.
- Bind every dynamic value through Moodle DML parameters. Do not concatenate
  identifiers, IDs, hostnames, profile fields, or claims into SQL.
- Shared courses, parent companies, delegated managers, site operations, and
  scheduled tasks require explicit semantics; a generic query rewriter cannot
  enforce them safely.
- Add indexes only through XMLDB after measured PostgreSQL query-plan evidence.
- Run `make operational-baseline` after every source-pin change and retain
  cross-company leakage tests as an independent control.
- See [IOMAD operational gap assessment](iomad-operational-gap-assessment.md)
  for the reviewed 5.1 findings.

## Administrative CLI

- Project administrative commands are dry-run plans unless `--apply` is
  explicit.
- Resolve companies, courses, and allocations through stable shortnames,
  idnumbers, external IDs, and immutable references, never operator-supplied
  database row IDs.
- Use IOMAD/Moodle APIs for writes. Direct table mutation from project CLI
  commands is prohibited.
- Isolation scans are read-only and write protected, pseudonymized reports
  below dataroot. Automated repair is prohibited.
- See [Administrative CLI operations](cli-operations.md) for commands, exit
  codes, and pipeline controls.

## Commercial Reporting

- Commercial reporting plugins are disabled by default and are not bundled.
- Do not treat `$USER->iomad_companyid`, a profile field, a hostname, or a
  client-supplied company ID as an authorization boundary.
- Resolve company and department scope through reviewed IOMAD APIs and current
  context capabilities. A missing scope denies tenant reporting.
- Scheduled exports must reauthorize their creator and recipients at execution
  time and use protected storage with bounded retention.
- External analytics requires a reviewed DPA, field-level data-flow inventory,
  residency, subprocessors, retention, deletion, and incident obligations.
- Follow [Commercial reporting integrations](commercial-reporting-integrations.md)
  before admitting LearnerScript, IntelliBoard, or another reporting engine.
