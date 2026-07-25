# Tenant Master Data Model

The plugin schema is metadata and domain definition, not a parallel LMS.

| Table suffix | Purpose |
|---|---|
| `tenant` | One-to-one stable trust/company link and tenant type |
| `acadyear` | Tenant academic-year lifecycle |
| `master` | Boards, mediums, grades, programmes, semesters, streams, subjects, templates and policies |
| `relation` | Versioned many-to-many academic applicability |
| `rule` | Versioned assessment, section, certificate, progression and projection expressions |
| `rolemap` | Business role to native role, manager type and scope |
| `defset` / `defitem` | Immutable shared/type-specific default versions |
| `mapping` | Native target ID, managed fields, desired/native hashes and last sync |
| `dirty` | Debounced automatic projection work |
| `job` / `jobitem` | Parent and item synchronization evidence |
| `drift` | Field-level platform/master/conflict findings and explicit resolution |
| `valissue` | Structured tenant/module/record/field validation result |
| `audit` | Append-only, redacted operational evidence |
| `batch` / `batchrow` | Immutable import manifest and normalized row plan/report |
| `rollover` / `rollitem` | Previewed, resumable academic-year transition |

## Native Records Not Duplicated

Full rows from these native domains are deliberately absent from plugin tables:

- IOMAD companies, departments, company users, company courses and licences;
- Moodle users, roles, contexts, categories, courses, cohorts, groups and
  enrolments;
- gradebook, activity, completion, certificate, log and historical records.

The UI reads current native values and joins them to stable mapping metadata.

## Stable Identity

- Tenant: `trust_code`.
- Academic definitions: tenant + type + `externalid`; `code` is also unique per
  tenant/type.
- Native categories/courses: `TM:<TRUST>:<TYPE>:<EXTERNAL_ID>`.
- Cohorts/groups/certificates use the same tenant-prefixed convention.
- Import packages match external IDs, shortnames, idnumbers, and checksums,
  never display names.

Keys are restricted to 1-100 characters beginning with an alphanumeric
character and containing only letters, numbers, dot, underscore, colon, or
hyphen.

## Field Ownership

Managed fields are explicit per component. Typical examples:

| Component | Managed fields |
|---|---|
| IOMAD company | name, shortname, code, address/host/approved branding fields |
| IOMAD department | name, shortname, parent |
| Course category | name, idnumber, description, parent, visibility |
| Course | fullname, shortname, idnumber, summary, category, format, dates, visibility |
| Cohort/group | name, idnumber, description and native scope |
| IOMAD certificate | name, intro, delivery and certificate layout settings |

Unmanaged native fields are not overwritten. Drift resolution is explicit:
accept native as the baseline, restore managed values through an API, or ignore
the finding.
