# Commercial Reporting Integrations

LearnerScript and IntelliBoard are optional procurement candidates. Neither
plugin is bundled, licensed, configured, or represented as compatible with the
pinned IOMAD 5.1 release by this repository.

## Evidence Review

| Candidate | Public evidence | Current decision |
|---|---|---|
| LearnerScript | The vendor describes IOMAD dashboards, role-based reports, scheduling, and PDF/XLS/ODS/CSV exports. Its public release notes explicitly identify Moodle 4.0, and a later article identifies Moodle 4.1. | Candidate only. Obtain the exact licensed release, written IOMAD 5.1/PHP 8.2-8.4/PostgreSQL support, and an upgrade policy before admission. |
| IntelliBoard | The Moodle plugin directory identifies `local_intelliboard` as a certified Moodle integration with dashboards and predictive analytics. Public material reviewed for this baseline does not establish IOMAD 5.1 tenant isolation. | Candidate only. Require written IOMAD 5.1 support, data-flow documentation, residency/retention terms, and proof that company managers cannot query other companies. |

Pricing and support terms are procurement inputs and must be revalidated with
the vendor. They are not encoded in deployment configuration.

## Reviewed Sources

Reviewed on 2026-07-25:

- LearnerScript vendor material: [IOMAD compatibility](https://learnerscript.com/learnerscript-is-now-compatible-with-iomad/),
  [features](https://learnerscript.com/features/),
  [release notes](https://learnerscript.com/release-notes/), and
  [Moodle 4.1 announcement](https://learnerscript.com/learnerscript-compatible-with-moodle-4-1/).
- IntelliBoard distribution metadata:
  [Moodle plugin directory](https://moodle.org/plugins/local_intelliboard).
- The supplied
  [scheduled-report article](https://mindfieldconsulting.com/how-to-automatically-send-moodle-reports/)
  is secondary implementation guidance, not vendor evidence of IOMAD 5.1
  compatibility.

Vendor pages and plugin-directory metadata can change. Admission requires
evidence tied to the exact artifact, not a generic product page.

## Rejected Configuration Patterns

Do not deploy either JSON prompt supplied as a configuration payload.

- `$USER->iomad_companyid` is not an approved security boundary. Resolve
  company membership and parent/child scope through reviewed IOMAD APIs and
  capabilities.
- A null company scope must deny the tenant report. It must not silently fall
  back to site-wide data.
- Do not synchronize a profile field directly into IOMAD tables. Use stable
  external IDs and supported IOMAD/Moodle APIs.
- Custom SQL is not approved merely because it contains a company join.
  Every query, filter, drill-down, count, cache entry, export, and scheduled
  task must be tested for cross-company leakage.
- Site-administrator dashboards must not become visible to company managers
  through widget configuration or role switching.

## Admission Manifest

Licensed artifacts belong in the ignored
`commercial-integrations/artifacts/` directory or an equivalent protected CI
artifact store. They must never be downloaded from a floating URL during an
image build.

Create a local manifest and run the admission gate:

```bash
cp commercial-integrations/reporting.env.example \
  commercial-integrations/reporting.env
make reporting-validate \
  REPORTING_MANIFEST=commercial-integrations/reporting.env
```

The gate requires an exact plugin version, SHA-256, written IOMAD 5.1
compatibility reference, license approval, DPA approval, and recorded
tenant-isolation acceptance. It validates the package but does not install it.
Installation requires a separately reviewed change under `iomad-overrides/`
and the normal plugin admission pipeline.

## Tenant Acceptance

Before promotion, test at least:

1. A company manager sees only its company and explicitly authorized child
   companies.
2. Department managers see only assigned departments.
3. Course, cohort, group, grade, completion, activity, license, and time
   metrics exclude unauthorized companies.
4. Filters, search suggestions, chart labels, drill-down links, caches, and
   exports preserve the same scope.
5. Scheduled jobs re-check the creator and every recipient at execution time.
6. Removed roles, suspended users, expired licenses, and moved users lose
   report and export access immediately.
7. Report files use protected storage, short-lived downloads, retention
   limits, and auditable delivery.
8. Parent-company aggregation is an explicit union of authorized descendants,
   not a site-wide fallback.
9. PostgreSQL query plans and cron schedules remain within the agreed load
   budget using production-scale sanitized data.
10. Backup, restore, clean install, previous-version upgrade, mobile, RTL, and
    accessibility acceptance pass with the exact licensed artifact.

## Scheduled Delivery

Use the vendor scheduler only after the acceptance suite passes. Schedules
must persist a stable company identifier, report definition version, format,
recipient policy, retention policy, and creator identity. At each run, resolve
current capabilities again, generate in protected storage, audit the delivery
without personal data, and delete expired files. Never pass arbitrary
recipient addresses or SQL through a generic CLI command.

## Selection Guidance

Choose only after a staging proof of concept:

- Prefer an on-premises implementation when data must remain in the IOMAD
  environment and the vendor provides a supported IOMAD 5.1 build.
- Consider an external analytics service only after documenting every outbound
  field, subprocessors, residency, retention, deletion, and tenant-partition
  control.
- Compare production-scale query load, parent-company reporting, scheduled
  delivery, accessibility, export security, upgrade lead time, and total
  operational ownership. Active-user count alone is not a sufficient
  selection criterion.
