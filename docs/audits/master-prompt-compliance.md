# Master Prompt Compliance

## Review Scope

This audit reconciles the consolidated 4,123-line prompt received on
2026-07-25 with repository source, configuration, tests, and documentation.
Repeated marketing statements were treated as desired capabilities, not proof
of production readiness. Unsupported direct database writes, floating images,
duplicate certificate engines, and unsafe tenant-query interception were not
implemented.

## Result

| Requirement group | Repository evidence | Status |
|---|---|---|
| Pinned official IOMAD 5.1 source | `versions.env`, detached bootstrap, source metadata | implemented; runtime source check pending |
| PHP/PostgreSQL baseline and `/public` web root | image, Compose, compatibility matrix | statically validated |
| Overrides outside upstream checkout | sync/capture scripts and regression test | validated |
| Install/update/backup/restore pipelines | scripts, Make targets, GitHub workflows, runbooks | static gates pass; restore runtime drill pending |
| School and university packs | schemas, sanitized samples, validate/plan/apply tooling | file validation implemented; CLI apply pending |
| Tenant imports | `local_institutionpack` modes and tests | implemented; runtime PHPUnit pending |
| White-label/theme/page-builder/product plugins | tracked overrides and capability matrix | implemented by admitted scope; browser acceptance pending |
| Commerce/WordPress/SSO/reporting boundaries | project plugins and commercial admission policy | implemented where source is present; external providers require credentials |
| Local Floci architecture | pinned Floci/UI/AWS CLI, four-layer Compose, provisioner | static validation implemented; emulator runtime pending |
| Service catalogue and status | validated DAG, rich metadata, protected HTML/JSON | implemented; runtime request pending |
| Health and observability | health contracts, Prometheus, OTel, Loki, Tempo, Grafana dashboard | static validation implemented; failure drill pending |
| Exception management | stable HTTP taxonomy, safe problem details, counters, runbooks | implemented; PHPUnit pending |
| Gamification and global events | company ledger, badges API, role projections, dashboard | implemented; runtime tenant tests pending |
| Conversational learning | HMAC/replay protection, manager/gateways, fixed commands | implemented; provider acceptance pending |
| SCORM/H5P adapters | trusted event adapters and idempotent rewards | implemented; browser/content runtime pending |
| Documentation governance | canonical catalogues, runbooks, CODEOWNERS, PR checklist, audit trail | implemented; external-link owner review remains |

## Security Decisions

- IOMAD company membership and course assignment are checked before reads or
  writes in project tenant workflows.
- Site administrator is not used as a tenant role.
- Certificate rendering remains owned by official `mod_iomadcertificate`.
- Workbook macros are operator-only and never executed in CI or production.
- Logs and metrics exclude personal, tenant, content, credential, and payment
  dimensions.
- Database rollback after migration means matching database+dataroot restore
  with the previous immutable image, never a database downgrade.

## Admission

This change is not production-admitted until Docker-dependent PHPUnit,
clean-install, previous-version upgrade, demo-pack apply-twice, tenant-hostname
smoke, accessibility/RTL/mobile browser, backup/restore, and observability
failure drills execute successfully in a permitted runtime.

