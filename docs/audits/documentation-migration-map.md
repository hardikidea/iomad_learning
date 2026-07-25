# Documentation Migration Map

## Rules

- Canonical chapters own complete guidance; legacy entry points become concise
  links only after migration.
- Immutable dated acceptance evidence remains unchanged.
- Third-party plugin notices remain with their source packages.
- Cost and pricing material remains separate from engineering documentation
  and requires owner revalidation.
- Every new implementation introduced by the consolidated prompts must update
  its owning chapter, service catalogue, test strategy, security boundary, and
  runbook in the same change.

## Map

| Existing source/section | Canonical target | Action | Validation | Status |
|---|---|---|---|---|
| `README.md` version/quick start | `README.md`, `docs/02-getting-started/local-setup.md` | Rewrite and link | Execute quick start contract | Planned |
| `IOMAD_PROJECT_CONTEXT.md`, compatibility matrix | `docs/15-reference/version-matrix.md` | Merge | Compare `versions.env` and plugin metadata | Planned |
| `architecture.md`, AWS summary, ADRs | `docs/03-architecture/architecture-overview.md` | Rewrite and link ADRs | Compare Compose/Terraform | In progress; boundaries and ADR added |
| Tenant model and role mapping | `docs/03-architecture/tenant-architecture.md`, `docs/05-iomad-domains/users-roles-and-capabilities.md` | Merge | Capability and isolation tests | Planned |
| New global events/gamification prompts | `docs/05-iomad-domains/gamification-and-global-events.md` | New evidence-backed chapter | PHPUnit, runtime, cross-company denial | Implemented; dashboard and communication update complete; runtime admission pending |
| New SCORM/H5P telemetry prompts | `docs/05-iomad-domains/interactive-content.md` | New evidence-backed chapter | Package validation and event-adapter tests | Implemented; runtime admission pending |
| New role-aware UX prompts | `docs/04-development/role-aware-ux.md` | New evidence-backed chapter | Browser role/RTL/reduced-motion tests | Implemented; browser acceptance pending |
| White-label/theme/customizer docs | `docs/04-development/theme-and-page-experience.md` | Merge and resolve conflict | Theme config, PHPUnit, browser checks | Planned |
| Page-builder catalogue | `docs/04-development/page-builder.md` | Move and expand | Catalog test and import/export | Planned |
| Setup/Docker/local Floci | `docs/02-getting-started/local-setup.md`, `docs/09-infrastructure/local-infrastructure.md` | Split and merge | Execute validation and health checks | Planned |
| Data packs/onboarding/demo blueprint | `docs/02-getting-started/local-test-data.md`, `docs/05-iomad-domains/tenant-onboarding.md` | Merge and correct paths | Pack validate/plan/apply twice | Test-data chapter completed; onboarding merge pending |
| CLI operations and Make targets | `docs/15-reference/command-reference.md` | Merge | Machine-check command names | Planned |
| Environment/configuration material | `docs/15-reference/configuration-reference.md` | Merge | Compare `.env.example`, Compose, Terraform | Planned |
| Commerce/WordPress/WhatsApp prompts | `docs/05-iomad-domains/notifications-and-integrations.md` | Merge and extend | Webhook, replay, privacy, provider tests | WhatsApp boundary completed; connector merge pending |
| Feature matrix/product acceptance | `docs/01-overview/capability-register.md`, `docs/08-testing/acceptance-policy.md` | Separate status from policy | Repository evidence links | Planned |
| Dated acceptance result | `docs/acceptance-results-2026-07-25.md` | Preserve baseline evidence and append clearly dated retry results | Check local links and release identity | Baseline retained; retry status appended |
| Security/gap assessment/plugin admission | `docs/13-security/*`, `docs/04-development/plugin-development.md` | Split and cross-link | Threat and isolation review | Planned |
| Monitor/observability prompt | `docs/11-operations/*` | Replace checklist-only guide with catalogues | Endpoint, config, and failure validation | Catalogues, dashboard, metrics, alerts, and runbooks implemented; runtime failure drill pending |
| CI/pipeline/deployment | `docs/10-ci-cd/*` | Split and correct repository names | Compare workflows/actions | Planned |
| Backup/upgrade/rollback/update | `docs/07-data/backup-and-restore.md`, `docs/10-ci-cd/upgrade-process.md`, `docs/10-ci-cd/rollback-process.md` | Merge into three authorities | Backup/restore and upgrade drills | Planned |
| Operator/incident runbooks | `docs/12-runbooks/*` | Split by failure mode | Drill table and command validation | Failure-mode runbooks implemented; live drills pending |
| Terraform README | `terraform/README.md`, canonical infrastructure chapters | Keep implementation-local detail; link concepts | Terraform validate | Planned |
| Academic rollover | `docs/05-iomad-domains/academic-rollover.md` | Expand | Pack dry run and rollback review | Planned |
| ADR template/index | `docs/adr/*`, `docs/14-governance/architecture-decisions.md` | Add missing impacts and index | Local links and numbering | Ownership/change-control chapter added; ADR numbering migration pending |
| Cost/pricing | `docs/business/*` | Move only after owner approval | Date/currency/source review | Owner review |
| Third-party plugin docs | Original package paths | Retain; project docs link admission status | License and compatibility gate | Retained |

## Required Canonical Chapters

The first implementation pass will create only relevant chapters:

```text
docs/
  index.md
  01-overview/
  02-getting-started/
  03-architecture/
  04-development/
  05-iomad-domains/
  07-data/
  08-testing/
  09-infrastructure/
  10-ci-cd/
  11-operations/
  12-runbooks/
  13-security/
  14-governance/
  15-reference/
  audits/
  adr/
  diagrams/sources/
  templates/
```

Empty or repository-irrelevant chapters will not be created.
