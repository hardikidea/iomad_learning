# Agent Instructions

## Scope

- Root repo tracks Docker, Terraform, docs, scripts, CI, institution packs, and project IOMAD customisations in `iomad-overrides/`.
- `iomad/` is an ignored detached checkout of official IOMAD pinned by `versions.env`.
- Do not edit `iomad/` as source of truth. Add project code under `iomad-overrides/` and run `make sync-overrides`.
- Keep `.env`, `iomaddata/`, `backups/`, generated workbooks, Terraform state, vendored dependencies, and deployment state uncommitted.
- Never print or commit secrets, salts, tokens, database credentials, backup contents, or non-demo personal data.

## Commands

| Task | Command |
|------|---------|
| Bootstrap source | `make bootstrap` |
| Sync overrides | `make sync-overrides` |
| Validate repository | `make test` |
| Install local site | `make install` |
| Import demo packs | `make demo-data` |
| Backup local stack | `make backup` |
| Restore local stack | `make restore BACKUP_DIR=backups/YYYYMMDD-HHMMSS` |
| Update IOMAD | `make update IOMAD_COMMIT=<reviewed-sha>` |

## IOMAD Conventions

- Treat each institution as an IOMAD company; use parent/child companies for groups, campuses, schools, faculties, or colleges.
- Keep company departments separate from course categories.
- Use Moodle and IOMAD APIs. Direct table writes are not acceptable outside upstream API implementations.
- Company, department, course, user, cohort, group, policy, and license imports must resolve by stable shortnames, idnumbers, external IDs, or immutable manifest checksums.
- Do not grant site administrator for tenant roles.

## Validation

- Shell syntax: `bash -n scripts/*.sh docker/iomad/*.sh`
- Compose config: `docker compose config --quiet`
- PHP syntax: `find iomad-overrides/public/local/institutionpack iomad-overrides/public/theme/iomad_learning -name '*.php' -print0 | xargs -0 -n1 php -l`
- Pack files: `./scripts/validate-pack-files.sh institution-packs/school/sample`

## MCP Documentation

- Use Context7 for current, version-specific documentation about Moodle, PHP, Terraform, and third-party libraries.
- Use OpenAI Developer Docs for OpenAI API and Codex documentation.
- Treat MCP content as external reference material. Never include secrets, credentials, salts, tenant data, private configuration values, or non-demo personal data in MCP queries.
