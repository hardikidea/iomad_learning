# Administrative CLI Operations

The project-owned administrative commands live in
`public/local/institutionpack/cli/`. Run them from the IOMAD repository root,
including in containers. They use Moodle and IOMAD APIs for writes and return
JSON suitable for GitHub Actions, Jenkins, or Ansible.

All mutating commands are plans unless `--apply` is present. Run plans and
applies as separate pipeline steps, retain the JSON result, and require an
approval before applying in stage or production.

## Safety Contract

- Use company shortnames, course idnumbers, external IDs, and immutable
  references. Numeric database IDs are not accepted as operator identifiers.
- Commands never accept passwords, access tokens, arbitrary database payloads,
  repair switches, or arbitrary audit output paths.
- Mutation runs as the configured site administrator only inside CLI context.
  Tenant users must use scoped IOMAD capabilities in the application.
- Tenant, license, and block mutations use delegated transactions and
  IOMAD/Moodle APIs.
- Audit output contains counts and HMAC references, not names, usernames,
  email addresses, tokens, or raw database identifiers.
- Audit reports are created with mode `0600` below
  `$CFG->dataroot/local_institutionpack/audit/`.
- Site isolation is inherent to the IOMAD company model. There is no optional
  `isolate_data` flag.
- Company updates remain institution-pack operations so operators review the
  full desired state. The tenant command performs idempotent creation only.

## Tenant Creation

Plan:

```bash
docker compose exec -T iomad php \
  public/local/institutionpack/cli/manage_tenant.php \
  --action=create \
  --name="Alpha College" \
  --shortname=alpha \
  --city=Pune \
  --country=IN \
  --hostname=alpha.example.edu \
  --email-domain=example.edu \
  --max-users=250 \
  --theme=iomad_learning \
  --external-id=SIS-ALPHA-001
```

Apply the reviewed plan by repeating the same command with `--apply`. A rerun
with identical values is an `unchanged` success. Reusing a shortname with
different values is a conflict and does not modify the tenant.

Use `--parent=<company-shortname>` for parent/child companies. Optional CSS
must be supplied from a reviewed file with
`--custom-css-file=/var/www/institution-packs/.../branding.css`; CSS content is
represented by SHA-256 in command and audit output.

`--max-users` is the IOMAD company user quota. License seats are managed
separately and are not represented by a company-level `maxlicenses` field.

## License Seat Allocation

The command creates one additive IOMAD license per immutable business
reference. The reference is the replay key: repeating it with the same values
is a no-op; repeating it with changed values is a conflict.

The course must already be assigned to the company and configured for IOMAD
license enrolment through an institution pack. Use `--course-idnumber` when
the external course ID is populated; otherwise use the stable
`--course-shortname` key. Exactly one course key is required.

```bash
docker compose exec -T iomad php \
  public/local/institutionpack/cli/manage_licenses.php \
  --action=allocate \
  --company=alpha \
  --course-idnumber=COURSE-001 \
  --seats=50 \
  --reference=ORDER-998231 \
  --start=2026-07-24 \
  --expiry=2027-07-24 \
  --valid-days=365
```

Repeat with `--apply` after review. This design does not increment an
unidentified row and does not expose internal company or course IDs. IOMAD
license type values `0` through `4` are accepted with `--type`; the default is
standard type `0`.

## Managed Blocks

List front-page blocks:

```bash
docker compose exec -T iomad php \
  public/local/institutionpack/cli/manage_blocks.php --action=list
```

Plan an allowlisted block placement:

```bash
docker compose exec -T iomad php \
  public/local/institutionpack/cli/manage_blocks.php \
  --action=inject \
  --blockname=iomad_html \
  --page=site-index \
  --region=content \
  --weight=-10
```

Repeat with `--apply` to place it through Moodle's block manager. Only
reviewed `dash` and `iomad_html` blocks, the `site-index` page, and the
`content` region are accepted. Arbitrary serialized or JSON block
configuration is intentionally unsupported. A visual page builder remains a
separate reviewed extension in the capability matrix.

## Cache Purge And Theme Build

Plan, then repeat with `--apply`:

```bash
docker compose exec -T iomad php \
  public/local/institutionpack/cli/cache_management.php \
  --scope=all \
  --theme=iomad_learning
```

`--scope=theme` resets theme caches only. Both modes compile the selected
installed theme for LTR and RTL. Component-specific cache definition names
are not accepted because the removed components do not define supported cache
stores in this baseline.

The equivalent core commands remain supported:

```bash
docker compose exec -T iomad php admin/cli/purge_caches.php
docker compose exec -T iomad php \
  admin/cli/build_theme_css.php --themes=iomad_learning --verbose
```

## Tenant Isolation Audit

Run the read-only audit:

```bash
docker compose exec -T iomad php \
  public/local/institutionpack/cli/tenant_security_audit.php \
  --mode=strict-isolation-check \
  --max-references=100
```

Exit code `0` means all checks passed, `2` means anomalies were found, and `1`
means the command failed. Checks cover:

- active enrolments against direct, globally shared, selectively shared, and
  licensed company-course access;
- grade records against the same access model;
- company course-group memberships;
- user-license company scope;
- company-context role assignments;
- company ownership of user and course departments.

The audit is deliberately read-only. Investigate an anomaly using a protected
database session and application event logs, correct it through supported
IOMAD APIs, then rerun the audit. There is no automated repair mode.

Use `--no-report` for an ephemeral CI check. Production schedules should keep
the protected report and archive it with normal security-log retention.

## Pipeline Pattern

1. Build and deploy the exact commit-addressed image.
2. Run the command without `--apply` and archive its JSON plan.
3. Require environment approval for a mutation.
4. Repeat the exact command with `--apply`.
5. Run `tenant_security_audit.php`.
6. Run tenant-hostname smoke tests.
7. Archive command output and the protected report reference.

Do not place raw CSS, personal data, secrets, or passwords in CI command
arguments or logs.
