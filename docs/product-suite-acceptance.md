# Product Suite Acceptance Register

This register prevents a catalogue label from being treated as implemented
without executable evidence. The feature capability matrix is the public
status source; this document tracks the implementation evidence needed to
promote each item.

## Evidence Levels

| Level | Required evidence |
|---|---|
| Designed | Ownership, threat boundary, API choice, data lifecycle, and upgrade impact are documented |
| Implemented | Installable code and operator configuration exist |
| Integration tested | Moodle PHPUnit or Behat tests cover permissions, tenant isolation, state transitions, and failure paths |
| System tested | A clean IOMAD install and previous-release upgrade execute the workflow in containers |
| Production accepted | Provider sandbox, mobile/RTL/accessibility, backup/restore, monitoring, and security gates pass for the release image |

## Workstreams

| Workstream | Native foundation | Project component | Production acceptance dependency |
|---|---|---|---|
| AI authoring and SCORM | Core AI, question bank, course/module APIs, H5P | `local_aicoursecreator` | Configured production AI provider and tenant data-processing approval |
| Video course | Media filters, completion, course format API | `format_iomadvideo` | Browser/video-provider acceptance |
| Page builder and theme | IOMAD custom pages, blocks, Boost child theme | `local_iomadpagebuilder`, `block_iomadpagebuilder`, `theme_iomad_learning` | Visual, keyboard, mobile, RTL, and WCAG checks |
| Dashboard | Completion, enrolment, quiz, forum, notes APIs | `block_iomaddashboard` | Role matrix and cross-company checks |
| Reports | IOMAD reports, logstore, report builder | `local_tenantanalytics` | Metric definitions, retention policy, mail transport |
| Forms | Forms API, File API, Message API | `mod_tenantform` | Privacy/retention approval and malware-scanning policy |
| Grading | Gradebook and advanced grading APIs | `local_rapidgrader` | Teacher role matrix and assessment workflow acceptance |
| Commerce | IOMAD commerce, licenses, core payment API | `local_iomadcommerce` | Payment-provider sandbox and accounting/refund policy |
| WordPress/WooCommerce | Moodle external services and OAuth/OIDC | `local_iomadconnect` plus companion WordPress plugin | Separate WordPress deployment and optional paid WooCommerce extension licenses |
| Monitoring | Moodle checks, task API, infrastructure health | `tool_iomadmonitor` | Alert destinations and on-call ownership |

Current repository evidence:

| Component | Automated evidence |
|---|---|
| `local_aicoursecreator` | Quota, schema, draft, publisher and SCORM PHPUnit suites |
| `format_iomadvideo` | Format contract and playlist PHPUnit suites |
| `local_iomadpagebuilder` / block | 140-preset, 30-template and page-repository PHPUnit suites |
| `block_iomaddashboard` | Catalog, private to-do and tenant-scope PHPUnit suites |
| `local_tenantanalytics` | Sessionization, scope, report engine and scheduler PHPUnit suites |
| `mod_tenantform` / block | Validation, submission, entry, access, backup and restore acceptance |
| `local_rapidgrader` | Course scope, gradebook update and export PHPUnit suites |
| `local_iomadcommerce` | Product, order, webhook replay and privacy-lifecycle PHPUnit suites |
| `local_iomadconnect` | Event, catalogue, synchronization and privacy-lifecycle PHPUnit suites |
| WordPress companion | Standalone syntax and event-contract test |
| `theme_iomad_learning` | 251-token catalog, SVG icon, tenant-branding, footer, and settings validation |
| `tool_iomadmonitor` | Normal/degraded health-state PHPUnit suite and CLI checks |

The accepted local release evidence, immutable image digests, exact commands,
browser results, recovery set, and remaining external boundaries are recorded
in
[acceptance-results-2026-07-25.md](acceptance-results-2026-07-25.md).

## Non-Software Catalogue Items

The following labels require signed service definitions rather than PHP code:

- managed hosting;
- free migration;
- near-zero-downtime migration;
- specialist support;
- dedicated support and updates;
- premium plugin inclusion;
- test-site licensing;
- regular updates.

The repository must provide operational controls and evidence for these
services, but it must not claim commercial terms that have not been agreed.

## Release Rule

A capability is promoted to **Verified baseline** only when its implementation
and integration tests pass in CI. It is promoted to production accepted only
after external provider and browser acceptance evidence is attached to the
immutable release.
