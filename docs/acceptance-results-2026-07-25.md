# IOMAD Product Suite Acceptance Results

## Current Retry Status

The current immutable image includes the operability, observability,
global-events, gamification, H5P, SCORM, role-aware UX, messaging, and Tenant
Master changes. All available local static, runtime, clean-install, upgrade,
isolation, backup, and restore gates passed against the exact pinned source.
Production promotion still requires environment approvals and the external
provider inputs listed under Acceptance Boundaries.

Current retry validation:

| Gate | Result |
|---|---|
| Official commit page and detached local checkout | Passed; both resolve to `55b3128b8058d27f6cc4320850ca709ed5a792a9` |
| `make test` | Passed; shell syntax, plugin support, commercial-plugin exclusion, Compose contracts, 100 Markdown inputs, XMLDB, override mirroring, PHP syntax, retention regression, and both sanitized packs |
| Moodle PHPCS | Passed with Moodle CS `3.7.0` |
| `terraform fmt -check -recursive terraform` | Passed |
| Observability configuration | Passed |
| Grafana provisioning and alert/runbook references | Passed |
| Service, endpoint, exception, metric, SLO, failure, and runbook catalogues | Passed local-link, JSON, and required-file gates |
| Legacy demo/password workflow | Removed; canonical institution packs are the only supported demo-data path |
| Global-events role boundary | Company/parent aggregate projection implemented; department-manager company-wide grant removed |
| GitHub workflow and Docker YAML syntax | Passed |
| JSON syntax | Passed |
| Recovery-set checksum verification | Passed for `backups/20260725-175334` |
| Backup retention | Timestamp matcher regression passed; four superseded acceptance sets pruned after replacement verification |
| PHPUnit, clean install, pack import, and restore drill | Passed; 50 project test files and 111 test methods |
| Previous-version upgrade retry | Passed from `ff1e70a3169a02ab97ed0e35ca43c0d04ddb2d58` to the pinned commit |
| Ecosystem verifier | Passed 94/94 checks with Floci connected; zero warnings, failures, isolation anomalies, failed jobs, role mismatches, or report-budget breaches |
| Terraform semantic validation | Passed for bootstrap, dev, stage, and production using pinned AWS `6.52.0` and Random `3.9.0` providers |
| ShellCheck | Not run locally because the binary is absent; retained as a CI gate |

The latest retry adds:

- rich validated service metadata and protected HTML/JSON catalogue output;
- complete required HTTP/exception categories, 422 validation behavior,
  request deduplication, fixed problem details, and W3C trace validation;
- bounded exception, health-latency, cron, storage, task, recovery, and queue
  metrics;
- a provisioned read-only Grafana platform dashboard and source-controlled
  alerts;
- role-aware global-event dashboards, recent badges through the Moodle badge
  API, communication manager/gateway/chatbot classes, and `STATUS`,
  `MY BADGES`, `MY CODES`, and `HELP`;
- exact `event_page.mustache` and `telemetry_block.mustache` presentation
  artifacts with reduced-motion-aware feedback;
- a tenant-filtered official IOMAD certificate count without exposing codes in
  chat or creating a duplicate certificate engine;
- governance ownership, pull-request controls, documentation navigation,
  operational catalogues, focused runbooks, and validation/conflict/debt
  reports.

Required release admission commands:

```bash
make build
make clean-install-test
make phpunit
PREVIOUS_IOMAD_COMMIT=ff1e70a3169a02ab97ed0e35ca43c0d04ddb2d58 \
  make upgrade-test
make product-demo-data
make pack-validate PACK=institution-packs/school/sample
make pack-validate PACK=institution-packs/university/sample
./scripts/tenant-smoke-test.sh
make backup-restore-test
```

## Release Identity

| Item | Accepted value |
|---|---|
| Acceptance date | 2026-07-25 |
| Upstream repository | `https://github.com/iomad/iomad.git` |
| Upstream ref | `IOMAD_501_STABLE` |
| Reviewed commit | `55b3128b8058d27f6cc4320850ca709ed5a792a9` |
| IOMAD/Moodle release | `5.1.5 (Build: 20260608)` |
| Core version | `2025100605` |
| PHP | `8.3.32` |
| PostgreSQL | `16.14` |
| Accepted web image ID | `sha256:1071a3224c05b79f839e9aed3d7b9f0672bdea2c8be498ce8de0ff7c47ac4b1c` |
| Accepted cron image ID | `sha256:766fdcc1824031df91bb1a79fa5650d687fb5a70568bea772c9b9cf9986ca9a0` |
| Retained recovery set | `backups/20260725-175334` |
| Recovery manifest SHA-256 | `9890169cac6c39640a391fc046c5088389e1542bd7c830fa65a4cafb7eee147c` |

The image label and `/var/www/iomad/.iomad-source.env` both record the same
commit. The checkout is detached and production workflows do not use
`git pull` or a floating branch.

## Local URLs

| Surface | URL |
|---|---|
| Default IOMAD | `http://localhost:18080` |
| MailPit | `http://localhost:18025` |
| Trust tenant | `http://trust.localhost:18080` |
| School tenant | `http://school.localhost:18080` |
| University tenant | `http://university.localhost:18080` |
| Engineering child company | `http://engineering.localhost:18080` |

The explicit `:18080` port is the stable local endpoint. Docker Desktop may
also publish port 80, but it is not used as acceptance evidence.

## Executed Final Acceptance

The following release gates passed for the current accepted image:

- repository contracts, shell syntax, plugin compatibility, Floci structure,
  commercial-plugin exclusion, and canonical pack validation;
- clean IOMAD installation and sanitized school/university imports;
- first-pass post-commit IOMAD company-membership reconciliation, including
  exact native manager types and educator flags after deferred user observers;
- controlled upgrade from reviewed commit
  `ff1e70a3169a02ab97ed0e35ca43c0d04ddb2d58` to the accepted commit;
- project-owned PHPUnit suites, including tenant isolation, permissions,
  imports, AI/SCORM, video playlist, builder, dashboard, analytics, forms,
  grading, commerce, connector, theme, and monitor tests;
- idempotent product demo seeding, with every second-run operation reported as
  `unchanged`;
- PostgreSQL schema validation;
- deep health checks for database, cron, Redis, storage, jobs, recovery
  freshness, runtime security, integration queues, and tenant isolation;
- 94 read-only ecosystem checks covering plugin/schema/index contracts,
  scheduled tasks, Floci, seven role personas per tenant, relationship
  integrity, drift, telemetry, ten report families, privacy masking, and
  deterministic output;
- the default URL and all four configured tenant hostnames;
- database and dataroot recovery mutation rollback through the complete
  backup/restore pipeline;
- browser workflows for anonymous, site administrator, school principal,
  school teacher, school learner, and university learner roles;
- focus-mode activation and exit, including navigation suppression and
  accessible pressed-state updates;
- school and university commerce separation, learner purchases, company page
  builder, analytics, AI drafts, tenant forms, rapid grading, and the
  `iomadvideo` course format;
- negative browser checks where school and university learners attempted to
  open the other tenant's course and were redirected to their own company
  home;
- 390 by 844 mobile and forced RTL rendering without horizontal overflow;
- axe-core 4.10.3 WCAG A/AA scans with zero violations on the project theme
  customizer and school shop. Remaining incomplete nodes are upstream dynamic
  menu relationships requiring manual assistive-technology review.

Representative exact commands:

```bash
./scripts/test-repository.sh
./scripts/test-phpunit.sh
./scripts/test-clean-install.sh --yes
PREVIOUS_IOMAD_COMMIT=ff1e70a3169a02ab97ed0e35ca43c0d04ddb2d58 \
  ./scripts/test-upgrade-from-previous.sh --yes
./scripts/import-demo-packs.sh
./scripts/seed-product-demos.sh
docker compose exec -T iomad php admin/cli/check_database_schema.php
docker compose exec -T iomad \
  php public/admin/tool/iomadmonitor/cli/check.php --output=json --deep
./scripts/tenant-smoke-test.sh
BACKUP_REASON=final-product-suite ./scripts/backup.sh
./scripts/verify-backup.sh backups/20260725-175334
./scripts/test-backup-restore.sh --yes
make ecosystem-verify \
  VERIFY_ARGS="--company=GV_SCHOOL,NBU_ENGINEERING --format=table \
  --max-report-ms=5000 \
  --floci-url=http://host.docker.internal:4566/_floci/health"
```

Only `backups/20260725-175334` remains under the local backup root. Its
checksums pass, its manifest records 610 database tables, and `latest.env`
references the same manifest hash. The guarded retention command removed four
superseded complete sets only after dry-run review and replacement
verification.

## Demo Evidence

Both institution packs are sanitized and contain no real personal data.
Acceptance data includes:

- one school company and a university parent/child-company hierarchy;
- school and university categories, departments, courses, users, roles,
  enrolments, policies, branding, forms, grades, products, orders, and seats;
- two published company page definitions;
- deterministic AI draft fixtures and two published API-created courses;
- SCORM 1.2 exports for both AI fixtures;
- two commerce products, two orders, and two active learner seats per tenant;
- one submitted tenant form and one graded learner per tenant;
- school physics and university data-structures courses using
  `format_iomadvideo`.

## Acceptance Boundaries

The release provides functional project-owned equivalents for the requested
software catalogue, not copied proprietary plugins or assets.

The following require separate external acceptance and are not represented as
live production integrations:

- an AI provider, its credentials, data-processing approval, and output-quality
  review;
- PayPal or another payment provider, signed sandbox callbacks, settlement,
  refund, tax, and accounting policy;
- a separately deployed WordPress/WooCommerce site and any separately licensed
  subscription or membership extensions;
- an OIDC/OAuth identity provider for social login and coordinated logout;
- outbound production mail, SMS, video-provider, malware-scanning, and alert
  destinations;
- optional commercial analytics products.

Password replication between WordPress and IOMAD is intentionally not
implemented. Federated OIDC/OAuth identity and immutable external IDs provide
the requested one-identity outcome without copying password material.

The page builder's accepted front-page placement is a Moodle block placement;
its dedicated preview renders the complete definition. A full-width
tenant-specific front-page layout requires an approved theme layout placement
decision before production. The sanitized video courses prove course-format
selection and playlist behavior but do not bundle a copyrighted video binary.

Managed hosting, free migration, near-zero-downtime migration, specialist
support, included commercial licenses, test-site licensing, and update/support
hours are service commitments. The repository contains the infrastructure,
runbooks, checks, backup/restore controls, and deployment gates needed to
deliver them, but commercial terms require a signed service definition.
