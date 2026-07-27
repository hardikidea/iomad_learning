# Feature Capability Matrix

This register maps the requested product catalogue to the pinned IOMAD 5.1
release. Project components are functional equivalents built with Moodle and
IOMAD APIs; they do not copy proprietary code, assets, or product branding.

## Status Definitions

| Status | Meaning |
|---|---|
| Integration tested | Implemented in `iomad-overrides/` and covered by focused PHPUnit or repository contract tests |
| Runtime admission pending | Implementation and static contracts pass, but the changed component still requires clean-install and PHPUnit execution against the release image |
| Configured baseline | Native IOMAD/Moodle capability is installed and included in the release architecture |
| External acceptance | Code is present, but a licensed service, provider sandbox, credentials, or separately deployed WordPress site is required for live acceptance |
| Service policy | Staffing, support, migration, or commercial entitlement that must be defined in a service contract |
| Security replacement | The requested outcome is delivered through a safer design instead of the insecure mechanism named in the catalogue |

## Capability Register

| Area | Requested capabilities | Status | Implementation and evidence boundary |
|---|---|---|---|
| Managed platform | Managed hosting, low-downtime migration, scalable performance, backups, security and updates | Configured baseline | Immutable image, ECS/RDS/EFS/Redis/ALB Terraform, health checks, deployment gates, checksummed recovery sets, restore drills, SBOM and vulnerability workflows |
| Service delivery | Free migration, specialist support, dedicated support and included commercial licenses | Service policy | Runbooks and technical controls exist; price, response time, staffing, license entitlement and migration scope require a signed service definition |
| AI course creator | Tenant allowance, lesson/section generation, quizzes, activities, resources, editing, review and direct publishing | Integration tested | `local_aicoursecreator`; 300-credit tenant default, Moodle AI providers, schema validation, quota/audit records, draft review and Moodle API publication |
| H5P and SCORM | H5P activity blueprints and generated-course SCORM export | Integration tested | Core H5P plus plugin blueprints; standards-shaped SCORM 1.2 package exporter is covered by unit tests |
| Interactive learning telemetry | Mid-lesson SCORM commits, offline retry, H5P answer/completion mapping and duplicate prevention | Runtime admission pending | `local_iomad_scorm_gen`, `local_iomad_h5p_bridge`, and `local_global_events`; normal core tracking remains authoritative |
| Global events and gamification | Tenant-visible events, XP ledger, levels, badges, learner feedback and parent aggregates | Runtime admission pending | Company-scoped immutable ledger, stable idempotency, core badge API, aggregate-only parent projection and `block_gamification_telemetry` |
| Conversational learning | STATUS, MY BADGES and HELP through an optional messaging gateway | External acceptance | Signed/replay-safe webhook, HMAC address lookup and durable queue are present; a reviewed provider, runtime secrets, opt-in and live privacy acceptance are required |
| Live AI provider | Provider response quality, model availability and data-processing approval | External acceptance | Requires an enabled Moodle AI provider, credentials, tenant approval and provider-specific acceptance; no credentials are committed |
| Video course format | Large player, generated playlist, YouTube/Vimeo/uploaded media, responsive sidebar and progress | Integration tested | `format_iomadvideo`; six selectable layouts, media extraction, completion integration, mobile/RTL styles and no custom database tables |
| Course lifecycle | Draft/free/paid/closed catalogue states, expiry, suspension, recommendations and purchased-course view | Integration tested | `local_iomadcommerce`; company-scoped products, access duration, recommendations and learner purchases use IOMAD/Moodle APIs |
| Payments | Redirect checkout, PayPal-compatible lifecycle, refund notifications and full-refund revocation | Integration tested | Signed, replay-safe webhook state machine; no card data enters IOMAD; only plugin-owned enrolments are reversed |
| Live payment gateway | PayPal or another provider checkout, callback signing and reconciliation | External acceptance | Requires provider credentials, HTTPS callback URL, sandbox acceptance and accounting/refund policy |
| WordPress synchronization | Connection test, category/course sync, draft import, users, enrolments and selective cursor sync | Integration tested | `local_iomadconnect` plus `commercial-integrations/wordpress/iomad-connect`; typed restricted services, stable IDs, bounded cursors and idempotent event processing |
| WooCommerce | Products, one-click order enrolment, quantities/bulk seats, variations, free products and translation | Integration tested | Companion plugin maps WooCommerce order items to company products; parent-product metadata supports variations; item quantity maps to seats |
| WooCommerce extensions | Subscription renewals, memberships and extension-specific cancellation semantics | External acceptance | Standard completed/cancelled/full-refund order hooks are supported; each separately licensed extension needs live lifecycle acceptance |
| Single sign-on | Shared login/logout, social login and redirect policy | Configured baseline | IOMAD OIDC and Moodle OAuth foundations are installed; IdP metadata, logout and redirect behavior need provider acceptance |
| Same password | One identity across WordPress and IOMAD | Security replacement | OIDC/OAuth federation and immutable external IDs replace password replication; passwords are rejected from connector payloads |
| Automated registration | Federated user creation after purchase and asynchronous enrolment | Integration tested | Connector creates or updates users without passwords, then commerce assigns only tenant-valid course seats |
| Bulk purchase | Seat packs, multiple learner assignment, CSV assignment, notifications and status audit | Integration tested | Commerce quantities up to 10,000, exact `user_idnumber` CSV input, resumable idempotent assignment, message notifications and audit events |
| White label | Company logo/favicon, palette, typography, contact/email copy and hostname branding | Configured baseline | IOMAD company branding remains authoritative; `theme_iomad_learning` supplies maintainable fallbacks, tenant-aware header/footer styling, a local SVG icon system, and 251 allow-listed design tokens |
| Theme experience | Modern navigation, custom login, live customizer, focus mode, logos/fonts, six course layouts, mobile and RTL | Integration tested | Theme token catalogue has 711 assertions; responsive/RTL/reduced-motion SCSS, file API assets, live preview and focus controls are included |
| Page builder | Drag/drop builder, 139+ designs, 30+ templates, home/course/dashboard layouts and import/export | Integration tested | `local_iomadpagebuilder` and `block_iomadpagebuilder`; 140 presets, 30 templates, versioned JSON validation, drag reorder and CLI/UI import/export |
| Dashboard blocks | Progress, enrolled users, quiz attempts, analytics, members, notes, feedback, forums, course management and to-do | Integration tested | `block_iomaddashboard`; ten modes, capability checks, private to-do records and company-scoped data sources |
| Advanced reports | Course/student/learner engagement, LMS/course/activity time, visits, cohorts/groups and intelligent risk labels | Integration tested | `local_tenantanalytics`; recursive company scope, sessionized logs, ten report definitions and explainable risk labels |
| Report delivery | PDF, Excel, ODS and CSV export plus scheduled email | Integration tested | Bounded exports, scheduled tasks, tenant-scoped recipients, immutable schedule audit and mail transport abstraction |
| Commercial analytics | Optional third-party analytics engine | External acceptance | Not bundled. Licensed, checksum-pinned artifacts must pass compatibility, data-flow, privacy and cross-company acceptance before image admission |
| Forms | Entries, multi-page flow, conditional logic, enrolment/registration/login/custom forms, files and downloads | Integration tested | `mod_tenantform` and `block_tenantform`; nine templates, eleven field types, File API, validation and company access checks |
| Form operations | Branding, notifications, activity/dashboard placement, exports and cross-browser rendering | Integration tested | Moodle renderer/message/dataformat APIs, backup/restore support, privacy provider and responsive theme styles |
| Rapid grading | One-screen user/course/item grading, multiple techniques, reports and graphs | Integration tested | `local_rapidgrader`; gradebook APIs, numeric/scales, quiz/item modes, tenant course scope, exports and distribution graph |
| Course formats | Usable course formats and video-first delivery | Configured baseline | Core formats, supported `format_designer`, and project `format_iomadvideo`; the incompatible legacy format is excluded |
| Site monitor | Application/database/cache/storage/cron/queue/isolation monitoring | Runtime admission pending | `tool_iomadmonitor`; JSON/text CLI, service graph, liveness/readiness/startup, protected metrics, scheduled checks, throttled alerts, recovery freshness and deep tenant audit |
| Observability | OpenTelemetry collection, Prometheus, Grafana, Loki, Tempo, Alertmanager and black-box probes | Configured baseline | Optional pinned Compose profile, generated runtime credentials, bounded aggregate labels and fail-open dependency policy |
| Administrative CLI | Tenant, licenses, page placement, cache, imports, reports, grading, commerce and audits | Integration tested | Project CLI entry points use stable IDs, bounded inputs, explicit modes and Moodle/IOMAD APIs rather than direct table-writing scripts |
| Data packs | School/university canonical packs, workbook review, manifests, plan/dry-run/apply/resume/report | Integration tested | `local_institutionpack`, CSV/YAML schemas, checksums, immutable manifests and sanitized demos; workbook macros never run in CI or production |
| Translation/accessibility | Translation files, RTL, keyboard use, reduced motion and responsive layouts | Configured baseline | Moodle language APIs, semantic templates and project CSS/JS states; release acceptance still includes automated and manual WCAG checks |

## Implementation Rules

1. Project features live only in `iomad-overrides/`; the ignored upstream
   checkout remains reproducible and unmodified.
2. Every override declares IOMAD 5.1 support and passes the compatibility gate.
3. Company and department scope is mandatory in APIs, background tasks,
   reports, webhooks, exports, caches and CLI operations.
4. Password synchronization is prohibited. External identities use OIDC/OAuth
   and stable provider IDs.
5. Payment pages are hosted by reviewed providers. IOMAD accepts only signed,
   idempotent lifecycle events and never stores card data.
6. AI output remains a draft until an authorized human approves publication.
   Logs contain hashes and operational metadata, not prompts with personal data.
7. Workbooks are operator interfaces only. Canonical data remains versioned
   CSV/YAML and production/CI never executes macros.

## Promotion Checklist

Before an externally dependent item becomes production accepted:

- configure production credentials through Secrets Manager or runtime secrets;
- complete provider sandbox and negative/replay tests;
- verify tenant permissions and cross-company denial;
- complete desktop, mobile, RTL, keyboard and accessibility acceptance;
- pass clean install and previous-version upgrade tests;
- scan the final immutable image and publish its digest/SBOM;
- complete a matching database, dataroot and image restore drill;
- attach the evidence to the release approval.
