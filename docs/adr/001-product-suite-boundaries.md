# ADR 001: Product Suite Plugin Boundaries

## Status

Accepted for implementation.

## Context

The requested product catalogue combines native IOMAD and Moodle features,
project-owned application features, external integrations, and human-operated
services. Treating those as one plugin would create an unsafe capability
boundary and make upgrades, privacy review, tenant-isolation testing, and
rollback unnecessarily coupled.

The pinned IOMAD 5.1 source already provides company management, company
departments, licenses, commerce foundations, payment-account selection, custom
pages, H5P, SCORM playback, OAuth/OIDC, report builder, gradebook, feedback,
completion, scheduled tasks, and standard block placement.

## Decision

Implement missing software capabilities through these components:

| Component | Responsibility |
|---|---|
| `local_aicoursecreator` | Provider-neutral AI drafting, tenant quotas, human review, Moodle API publication, and constrained SCORM 1.2 export |
| `local_iomadpagebuilder` | Versioned page definitions, 140 reusable component presets, 30 page templates, import/export, preview, publication, and tenant ownership |
| `block_iomadpagebuilder` | Safe rendering of a published page on IOMAD custom pages, the site front page, dashboards, and course pages |
| `block_iomaddashboard` | Ten capability-aware dashboard widgets backed by Moodle and IOMAD APIs |
| `format_iomadvideo` | Video-first course presentation, generated playlist, focus mode, completion display, and responsive/RTL navigation |
| `mod_tenantform` | Multi-page conditional forms, templates, file uploads, notifications, entry management, and privacy controls |
| `local_tenantanalytics` | Tenant-scoped engagement/time reports, filters, exports, schedules, and immutable report-run audit |
| `local_rapidgrader` | Company/course/user grade workspace, supported grade API updates, exports, and visual summaries |
| `local_iomadcommerce` | Order/refund/bulk-seat state machine, recommendations, purchased-course view, signed webhooks, and IOMAD license orchestration |
| `local_iomadconnect` | Versioned WordPress/WooCommerce and HR identity contracts, cursors, idempotency, replay handling, and external functions |
| `tool_iomadmonitor` | Application dependency, queue, cron, storage, cache, tenant-routing, and integration health checks |

All components live in `iomad-overrides/public`. No component modifies the
ignored upstream checkout.

## Security Boundaries

1. A company identifier is resolved from the authenticated IOMAD context. A
   request-supplied company identifier is never trusted by itself.
2. Tenant managers need explicit component capabilities in the company
   context. Site administrator is not a tenant role.
3. Mutations use supported Moodle/IOMAD APIs. Project-owned tables are updated
   through plugin repositories and delegated transactions.
4. External callbacks require a timestamp, nonce, key identifier, HMAC
   signature, and idempotency key. Secrets are stored in environment-backed
   configuration and never returned or logged.
5. AI output remains a draft until a capable human approves publication.
   Prompts and audit records exclude passwords, tokens, and unnecessary
   personal data.
6. Payment card data never enters IOMAD. Payment providers or WooCommerce own
   checkout; IOMAD receives signed state transitions only.
7. Password synchronization is not implemented. WordPress and IOMAD use a
   shared OIDC/OAuth identity provider.

## Delivery Boundaries

- Core H5P creation, video embedding, completion, PayPal, OAuth/OIDC,
  translation, RTL, mobile rendering, and IOMAD licensing remain native
  capabilities and receive configuration and acceptance coverage.
- WordPress/WooCommerce, an identity provider, payment-provider credentials,
  outbound email, and a production AI provider are external systems. The
  repository supplies adapters, a local test counterpart where practical, and
  contract tests; live production acceptance requires real credentials and
  provider sandboxes.
- Migration assistance, specialist support, response hours, update service,
  and third-party licenses are service commitments. Runbooks and operational
  checks are software deliverables, but human service claims cannot be proven
  by an automated test.
- SCORM export initially covers generated content pages and supported
  static resources in a valid SCORM 1.2 package. Moodle activities with
  server-side behavior are linked or described and are not falsely converted
  into offline SCORM equivalents.

## Acceptance

Each component must pass PHP syntax and Moodle coding standards, install and
upgrade tests, privacy checks where data is stored, permission tests,
cross-company denial tests, PostgreSQL tests, and backup/restore review.
User-facing components also require desktop/mobile, keyboard, RTL, and
accessibility checks. External adapters require replay, invalid-signature,
out-of-order event, timeout, and retry tests.

## References

- [Moodle 5.1 AI subsystem](https://moodledev.io/docs/5.1/apis/subsystems/ai)
- [Moodle 5.1 APIs](https://moodledev.io/docs/5.1/apis)
- [Moodle local plugins](https://moodledev.io/docs/5.1/apis/plugintypes/local)
- [Moodle external functions](https://moodledev.io/docs/5.1/apis/subsystems/external/functions)
- [Moodle task API](https://moodledev.io/docs/5.1/apis/subsystems/task)

