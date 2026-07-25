# Existing Documentation Audit

## Purpose

This audit records the documentation state found before the 2026-07-25
consolidation. It covers repository-owned Markdown and text documentation,
including root, infrastructure, institution-pack, demo-data, integration, and
bundled third-party plugin documents. The ignored upstream `iomad/` checkout is
not a project documentation source.

## Evidence

- Repository root: `/Users/hardik.chauhan/Documents/learning/iomad_learning`
- Git state: no commits exist on `main`; meaningful Git update dates are
  unavailable for every document.
- Runtime source: `versions.env`, pinned commit
  `55b3128b8058d27f6cc4320850ca709ed5a792a9`.
- Link baseline: `./scripts/validate-docs.sh` passed for its 43-file input set.
- Implementation baseline: `make test` and `make phpunit` passed before this
  audit.
- Documentation tooling: local-link validation exists; Markdown style,
  external-link, Mermaid, spelling, and command-contract validation do not.
- Diagram baseline: no Mermaid diagram existed in repository documentation.

## Material Findings

| Severity | Source | Finding | Evidence | Action |
|---|---|---|---|---|
| High | `docs/theme.md` | States that only stock Boost is used and custom themes are prohibited. | `iomad-overrides/public/theme/iomad_learning` is installed, configured by `.env.example`, tested, and documented elsewhere. | Replace with a canonical white-label/theme chapter. |
| High | `docs/deployment-preparation.md` | Uses the old `hardikidea/eLearnMindset` repository and directory in clone, GitHub, and Terraform examples. | Current repository is `iomad_learning`; GHCR remains `ghcr.io/hardikidea/iomadlearning`. | Correct all source-repository examples and make organization/repository placeholders explicit. |
| High | `docs/site-monitor.md` | Documents unsupported `--format=json`. | `public/admin/tool/iomadmonitor/cli/check.php` accepts `--output=json|text`. | Correct in the canonical health chapter and retain a regression check. |
| Medium | `docs/compatibility-matrix.md` | Lists `local_institutionpack` `0.1.0` and `theme_iomad_learning` `0.1.0`. | Their current `version.php` files declare `0.2.0` and `1.0.0`. | Generate the canonical matrix from `versions.env` and plugin metadata checks. |
| Medium | `terraform/README.md` | Says local Docker bind-mounts `./iomad`. | `docker-compose.yml` runs the image with IOMAD source included and does not mount `./iomad`. | Correct the infrastructure chapter. |
| Medium | `docs/architecture.md` | Says some implemented components are only ownership names. | The named project plugins are present and have PHPUnit coverage. | Rewrite as an evidence-backed component and dependency map. |
| Medium | `README.md` | Contains setup, product, upgrade, backup, test, and documentation detail that duplicates specialist guides. | The documentation prompt requires a concise entry point and one canonical subject location. | Reduce to bootstrap commands and canonical chapter links. |
| Medium | Recovery guides | Backup, upgrade, rollback, update, and combined recovery information spans five files. | Commands and invariants repeat with small differences. | Merge into canonical upgrade, rollback, and recovery chapters; convert legacy files to links after migration. |
| Medium | Operations guides | `runbook.md`, `incident-runbook.md`, `site-monitor.md`, and portions of `backup-restore.md` overlap. | Repeated routine commands and incident containment rules. | Create a runbook index and focused failure runbooks. |
| Medium | Acceptance documents | Capability status, acceptance requirements, and dated results are repeated. | `feature-capability-matrix.md`, `product-suite-acceptance.md`, and dated results serve different but overlapping purposes. | Keep one capability register, one acceptance policy, and immutable dated evidence. |
| Medium | Demo blueprint | Uses the old product name and points to paths that do not exist in the repository. | Current sanitized packs are under `institution-packs/`. | Replace with canonical demo seed guidance and tested commands. |
| Low | ADR numbering | `001-product-suite-boundaries.md` does not follow the four-digit ADR naming convention and is absent from the ADR index. | ADR index lists only 0001 and 0002. | Preserve file for compatibility, add it to the index, and use four digits for new ADRs. |
| Low | Commercial/plugin README files | Bundled third-party documents contain vendor marketing and compatibility claims broader than the admitted repository release. | Project policy already narrows support through the compatibility gate. | Retain vendor notices unchanged; link to project admission policy rather than treating them as authoritative. |

## Inventory

Recommended actions use the controlled vocabulary from the documentation
consolidation brief.

| Source | Purpose | Accuracy | Duplication/conflict | Recommendation |
|---|---|---|---|---|
| `AGENTS.md` | Repository automation rules | Current | None | Keep unchanged |
| `README.md` | Repository entry point | Mostly current | Duplicates setup, recovery, and catalogue | Keep and update |
| `docs/IOMAD_PROJECT_CONTEXT.md` | Source identity | Current | Duplicates version matrix | Replace with canonical link |
| `docs/academic-rollover.md` | Academic rollover | Current but terse | None | Merge into chapter |
| `docs/acceptance-results-2026-07-25.md` | Immutable release evidence | Current at recorded date | Overlaps acceptance policy | Keep unchanged |
| `docs/architecture.md` | High-level architecture | Partly stale | Duplicates multiple domains | Merge into chapter |
| `docs/aws-architecture.md` | AWS summary | Current but incomplete | Duplicates Terraform README | Merge into chapter |
| `docs/aws-monthly-cost-estimate-inr.md` | Time-bound cost estimate | Requires periodic revalidation | Separate commercial planning domain | Requires owner review |
| `docs/backup-restore.md` | Recovery pipeline | Current and detailed | Overlaps rollback and upgrade | Merge into chapter |
| `docs/ci-cd.md` | Delivery pipeline | Current | Overlaps pipeline onboarding | Merge into chapter |
| `docs/cli-operations.md` | Administrative CLI | Current | Some command duplication | Merge into chapter |
| `docs/commerce-wordpress.md` | Commerce and connector | Current | None | Merge into chapter |
| `docs/commercial-reporting-integrations.md` | Commercial admission policy | Current | None | Keep and update |
| `docs/compatibility-matrix.md` | Version matrix | Stale plugin releases | Duplicates version pin | Merge into chapter |
| `docs/data-packs.md` | Pack contract | Current but terse | Overlaps institution-pack README | Merge into chapter |
| `docs/deployment-preparation.md` | AWS onboarding | Materially stale source names | Duplicates Terraform README and CI | Split across chapters |
| `docs/docker.md` | Local container summary | Current | Overlaps setup and Floci | Merge into chapter |
| `docs/feature-capability-matrix.md` | Product capability status | Current before new prompts | Overlaps acceptance policy | Keep and update |
| `docs/incident-runbook.md` | Incident response | Current but incomplete | Overlaps operator runbook | Merge into chapter |
| `docs/indian-school-product-pricing.md` | Commercial packaging | Time-bound and non-engineering | None | Requires owner review |
| `docs/iomad-operational-gap-assessment.md` | Rejected unsafe proposals and gaps | Current | None | Keep and update |
| `docs/local-cloud-floci.md` | Floci architecture/runbook | Current | Overlaps local infrastructure | Merge into chapter |
| `docs/page-builder-catalog.md` | Page components/templates | Current | None | Merge into chapter |
| `docs/pipeline-onboarding.md` | GitHub/AWS setup | Current but terse | Duplicates CI and deployment | Merge into chapter |
| `docs/plugin-compatibility.md` | Plugin admission policy | Current | None | Merge into chapter |
| `docs/product-suite-acceptance.md` | Acceptance evidence policy | Current | Overlaps capability register | Keep and update |
| `docs/rollback.md` | Rollback invariant | Current | Overlaps recovery guide | Merge into chapter |
| `docs/runbook.md` | Operator command index | Current but terse | Overlaps incident and setup | Replace with canonical link |
| `docs/security.md` | Security policy | Current | Broad subject span | Split across chapters |
| `docs/setup.md` | Local setup | Current but incomplete | Duplicates README/Docker | Merge into chapter |
| `docs/site-monitor.md` | Monitor operation | Incorrect CLI flag | Future observability overlap | Merge into chapter |
| `docs/tenant-onboarding.md` | Company onboarding | Current but terse | Overlaps packs | Merge into chapter |
| `docs/theme-customizer.md` | Current custom theme | Current | Conflicts with `theme.md` | Merge into chapter |
| `docs/theme.md` | Obsolete stock-theme policy | Incorrect | Conflicts with implementation | Replace with canonical link |
| `docs/update.md` | Upgrade redirect | Current | Redundant | Replace with canonical link |
| `docs/upgrade-backup-restore.md` | Recovery link page | Current | Redundant | Replace with canonical link |
| `docs/upgrade.md` | Upgrade flow | Current | Overlaps recovery guide | Merge into chapter |
| `docs/white-label.md` | White-label policy | Current | Overlaps customizer | Merge into chapter |
| `docs/adr/0001-aws-iomad-target-architecture.md` | AWS decision | Current | None | Keep and update |
| `docs/adr/0002-github-actions-oidc-ghcr-delivery.md` | Delivery decision | Current | None | Keep and update |
| `docs/adr/001-product-suite-boundaries.md` | Plugin boundary decision | Current | Missing from index | Keep and update |
| `docs/adr/README.md` | ADR index | Incomplete | None | Keep and update |
| `docs/adr/template.md` | ADR template | Missing required impact fields | None | Keep and update |
| `terraform/README.md` | Terraform operator guide | One stale local-runtime claim | Duplicates deployment guide | Keep and update |
| `institution-packs/README.md` | Pack quick reference | Current | Duplicates data-pack guide | Replace with canonical link |
| `commercial-integrations/wordpress/iomad-connect/readme.txt` | WordPress plugin metadata | Current | None | Keep unchanged |
| `iomad-overrides/demo-data/indian-school/course-activity-blueprint.md` | Legacy demo blueprint | Stale brand, paths, and printed shared passwords | Conflicted with current packs | Removed after canonical sanitized packs were validated |
| `iomad-overrides/public/admin/tool/courserating/README.md` | Third-party plugin notice | Vendor-owned | Broad compatibility claim | Keep unchanged |
| `iomad-overrides/public/admin/tool/courserating/CHANGELOG.md` | Third-party history | Vendor-owned | None | Keep unchanged |
| `iomad-overrides/public/blocks/dash/README.md` | Third-party plugin notice | Vendor-owned and marketing-heavy | Mentions paid tier not admitted here | Keep unchanged |
| `iomad-overrides/public/blocks/dash/changes.md` | Third-party history | Vendor-owned | None | Keep unchanged |
| `iomad-overrides/public/course/format/designer/README.md` | Third-party plugin notice | Vendor-owned | Broad compatibility claim | Keep unchanged |

## Consolidation Decision

The existing documentation is useful but is not yet an authoritative,
chapter-wise system. It is approved only as migration input. No legacy
document may be removed until its row in
`documentation-migration-map.md` is marked migrated and the replacement passes
link, command, version, and diagram validation.
