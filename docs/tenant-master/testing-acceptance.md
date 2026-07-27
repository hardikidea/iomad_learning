# Testing And Acceptance

## Automated Coverage

| Test | Evidence |
|---|---|
| Repository gate | Shell syntax, Compose, compatibility, docs, XMLDB, override mirror, PHP syntax and sanitized packs pass |
| Project PHPUnit | Project test files run with warnings and notices treated as failures on PHP 8.3/PostgreSQL 16 |
| Tenant Master PHPUnit | 13 test files and 47 declared test methods pass inside the complete project matrix |
| JSON and redaction helpers | Strict parsing and deterministic hashing |
| Default adoption | School/university catalogues, hierarchy, idempotency, active year |
| Native projection | Academic-year parent, categories, courses, company assignment |
| Native course metadata | 13 locked course custom fields, hierarchy values and read-back |
| Clean default state | Empty native company and Tenant Master profile sets pass ecosystem verification without seed data |
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

The pre-refactor demo acceptance run checked all legacy routes. Release 1.2.0
replaces those duplicate CRUD routes with native IOMAD links; the current
release gate rechecks the reduced Tenant Master navigation and native targets.
Anonymous access redirected to login. Desktop and 390-by-844 mobile checks had
no horizontal overflow, missing language strings, duplicate DOM IDs, unlabeled
controls, console errors, or page errors. A tracked pinned-source correction
for `block_iomad_mycourses` keeps its AMD selectors inside the module closure;
override hash validation prevents applying that correction to an unreviewed
upstream file. A forced RTL mobile structural check also had no document-level
overflow or JavaScript errors; wide native tables remained inside Moodle's
responsive horizontal scroller. A production release still needs a real
translated RTL language pack and human language review.

## Tenant Admin Release Evidence

Release 1.8.0 was rebuilt and validated locally on 2026-07-27:

- The dedicated **Tenants** Admin Tools tab inherited the selected IOMAD
  company and rendered 22 School tenant tiles.
- Every tile returned HTTP 200 with the company context preserved. Grade opened
  the focused Grade list and form rather than the combined academic catalogue.
- The 51-row combined catalogue filtered, sorted and paginated at 20 rows per
  page.
- Desktop at 1440 px and mobile at 390 px had zero tile overlaps, zero
  document overflow, 22 non-zero semantic SVGs and no browser console errors.
- The complete project-owned matrix passed all 59 configured test files. The
  final navigation and CRUD rerun passed 4 tests/34 assertions and 9 tests/38
  assertions respectively.
- Database schema and live Tenant Master validation passed with zero findings.

## Lifecycle And Recovery Evidence

| Gate | Result |
|---|---|
| Clean installation | Passed from an empty PostgreSQL database, Redis state and dataroot |
| Previous-release upgrade | Passed from reviewed commit `ff1e70a3169a02ab97ed0e35ca43c0d04ddb2d58` to the pinned commit |
| Schema and cache | Composer install, Moodle upgrade, schema check and cache purge passed |
| Tenant smoke test | Default URL and every configured tenant hostname passed after upgrade and restore |
| Recovery restore | Matching PostgreSQL, dataroot and immutable source state restored from the verified pre-drill set |
| Ecosystem validation | 94/94 live checks passed with Floci connected, zero warnings and zero failures |
| Final recovery set | `backups/20260727-153826`, manifest SHA-256 `d02f410dffe8c83cc7812e7b05799761c1fe346b7e4f389bba9812d59a1c205a` |
| Retention | Timestamp recognition regression-tested; superseded drill sets removed only after final-set verification |

The immutable web image ID is
`sha256:507f74ae4551f5d3b6dfc366927f6b241c4d3dee17400d14b3cbb04123e34b6f`;
the cron image ID is
`sha256:235b10a5ae5d706f798bd2313c3aa06453fdc57fb688deafc14d04aad023f6fa`.
Both images label the exact pinned IOMAD commit.

## Acceptance Checklist

- [x] One business tenant maps one-to-one to an IOMAD company.
- [x] School and university defaults are isolated and idempotent.
- [x] Grade, subject, role, course, and policy data map to real native records.
- [x] Company departments remain separate from course categories.
- [x] Native IOMAD is the only manual CRUD surface for supported operational records.
- [x] Tenant Master exposes academic masters and orchestration that have no complete native equivalent.
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
