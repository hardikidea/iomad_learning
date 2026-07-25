# Testing And Acceptance

## Automated Coverage

| Test | Evidence |
|---|---|
| Repository gate | Shell syntax, Compose, compatibility, docs, XMLDB, override mirror, PHP syntax and sanitized packs pass |
| Project PHPUnit | Project test files run with warnings and notices treated as failures on PHP 8.3/PostgreSQL 16 |
| Tenant Master PHPUnit | 9 test files, 24 tests and 97 assertions pass |
| JSON and redaction helpers | Strict parsing and deterministic hashing |
| Default adoption | School/university catalogues, hierarchy, idempotency, active year |
| Native projection | Academic-year parent, categories, courses, company assignment |
| Gradebook | Assessment category, attendance item, completion, pass grade |
| Failure path | Retryable item and non-truncated `completed_with_errors` job |
| Isolation | Cross-company users, courses, groups and enrolments rejected |
| Native user | No plugin identity/password copy, company role and intercepted mail |
| Role security | Exact IOMAD manager types, limited IT capabilities, canonical guardian role and no site administrator |
| Report privacy | Learner-level output is deterministically pseudonymized when PII capability is absent |
| Import | Manifest/checksum/tenant validation, plan and apply |

## Live Demo Evidence

Validated locally on 2026-07-25 against IOMAD commit
`55b3128b8058d27f6cc4320850ca709ed5a792a9`:

| Measure | School | University |
|---|---:|---:|
| Native IOMAD company users | 219 | 174 |
| Distinct native student-role users | 100 | 100 |
| Native departments | 12 | 11 |
| Company courses | 56 | 46 |
| Tenant Master native categories | 30 | 19 |
| Tenant Master native subject courses | 18 | 12 |
| Courses with native attendance grade item | 56 | 46 |
| Courses with completion enabled | 56 | 46 |
| Native IOMAD certificate activities | 56 | 46 |
| Active business-role mappings | 7 | 7 |

Both tenant validations returned zero errors, zero warnings, and zero blocking
issues. Drift detection returned zero open managed fields. Both automatic
queues ended with zero dirty, running, retryable, or blocked records.

Native projection read-back recorded 105 synchronized mappings for the school
and 78 for the university, with zero non-synchronized mappings. Each tenant
has all seven active business-role mappings.

All 14 Tenant Master sections were checked for both demo tenants: 28
authenticated desktop routes and 14 mobile routes returned HTTP 200.
Anonymous access redirected to login. Desktop and 390-by-844 mobile checks had
no horizontal overflow, missing language strings, duplicate DOM IDs, unlabeled
controls, console errors, or page errors. A tracked pinned-source correction
for `block_iomad_mycourses` keeps its AMD selectors inside the module closure;
override hash validation prevents applying that correction to an unreviewed
upstream file. A forced RTL mobile structural check also had no document-level
overflow or JavaScript errors; wide native tables remained inside Moodle's
responsive horizontal scroller. A production release still needs a real
translated RTL language pack and human language review.

## Lifecycle And Recovery Evidence

| Gate | Result |
|---|---|
| Clean installation | Passed from an empty PostgreSQL database, Redis state and dataroot |
| Previous-release upgrade | Passed from reviewed commit `ff1e70a3169a02ab97ed0e35ca43c0d04ddb2d58` to the pinned commit |
| Schema and cache | Composer install, Moodle upgrade, schema check and cache purge passed |
| Tenant smoke test | Default URL and every configured tenant hostname passed after upgrade and restore |
| Recovery restore | Matching PostgreSQL, dataroot and immutable source state restored from the verified pre-drill set |
| Ecosystem validation | 94/94 live checks passed with Floci connected, zero warnings and zero failures |
| Final recovery set | `backups/20260725-185646`, manifest SHA-256 `dbf4071874a02926f6cbfe502e79691713bc68fd6629006e880ac9ef63a47a2a` |
| Retention | Timestamp recognition regression-tested; superseded drill sets removed only after final-set verification |

The immutable web image ID is
`sha256:30882f97e36fef2f1baabda8a013fe110c2f4f77b2edb366b8d2babae5a172d9`;
the cron image ID is
`sha256:a816b496ec6969e2b4dc82c1c8ccb517c6bd2e03a21301d0a91a922371f55ae0`.
Both images label the exact pinned IOMAD commit.

## Acceptance Checklist

- [x] One business tenant maps one-to-one to an IOMAD company.
- [x] School and university defaults are isolated and idempotent.
- [x] Grade, subject, role, course, and policy data map to real native records.
- [x] Company departments remain separate from course categories.
- [x] Every normal workflow is available in the plugin UI.
- [x] Automatic post-CRUD processing, locks, retries, read-back, audit and drift
  are implemented.
- [x] Import is versioned, checksummed, tenant-bound, resumable, and rejects
  credentials/personal identity columns.
- [x] No tenant role receives site administrator.
- [x] No unsupported attendance or workbook-macro plugin is installed.
- [x] Destructive rollover is previewed and requires backup evidence.
- [ ] Production identity, mail, payment, AI, OIDC, WordPress, DNS, and AWS
  credentials remain environment-specific and are intentionally not enabled by
  sanitized demo data.

The final unchecked item is a deployment input, not a code shortcut. Provider
integrations must remain fail-closed until their secrets, contracts, privacy
review, and staging acceptance are complete.
