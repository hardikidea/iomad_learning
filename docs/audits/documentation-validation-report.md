# Documentation Validation Report

## Identity

| Item | Value |
|---|---|
| Date | 2026-07-25 |
| Repository | `iomad_learning` |
| IOMAD ref | `IOMAD_501_STABLE` |
| IOMAD commit | `55b3128b8058d27f6cc4320850ca709ed5a792a9` |
| Local checkout | detached, clean for tracked upstream files |
| PHP target | 8.2-8.4; default 8.3 |
| PostgreSQL target | 15+; default 16 |

## Passed

| Command/gate | Result |
|---|---|
| `make test` | repository contracts, XMLDB, compatibility, PHP syntax, override mirror, docs, and both packs passed |
| Moodle PHPCS `3.7.0` | all configured project components passed |
| `terraform fmt -check -recursive terraform` | passed |
| three Compose configurations | rendered successfully |
| repository YAML parse | passed |
| repository JSON parse | passed |
| `scripts/validate-observability.sh` | dashboard JSON, alerts, runbooks, pins, and Compose passed |
| `scripts/validate-docs.sh` | local links, required chapters, inventory JSON, and Mermaid fence contracts passed |
| `scripts/verify-backup.sh backups/20260725-085605` | all recovery-set checksums and required artifacts passed |
| `make backup-prune KEEP_BACKUPS=3` | dry run; no old set exists outside the keep window |
| upstream identity | official remote, exact pinned SHA, detached checkout |
| floating-image/production-pull scan | no matched deployment pattern |
| legacy demo scan | hardcoded password seeder and stale CSV set removed |

## Blocked Or External

| Gate | Reason |
|---|---|
| PHPUnit | sandbox cannot open `/Users/hardik.chauhan/.docker/run/docker.sock` |
| image build and clean install | same Docker socket restriction |
| previous-version upgrade | same restriction; previous reviewed SHA is `ff1e70a3169a02ab97ed0e35ca43c0d04ddb2d58` |
| demo pack CLI plan/apply twice | requires the application container |
| tenant-hostname and browser tests | requires rebuilt runtime containing this source |
| backup/restore mutation drill | requires Docker control and is intentionally destructive |
| observability failure drill | requires the optional runtime profile |
| Terraform semantic validation | AWS `6.52.0` and Random `3.9.0` provider packages are not cached |
| ShellCheck, yamllint, Hadolint, Trivy | local binaries absent; retained as CI gates |
| MkDocs strict build | local `mkdocs` executable absent |
| external integrations | require approved provider accounts, credentials, contracts, and staging |

Docker reports:

```text
permission denied while trying to connect to the docker API at
unix:///Users/hardik.chauhan/.docker/run/docker.sock
```

Terraform reports that the pinned provider packages are not cached in
`.terraform/providers`.

## Decision

Static repository and documentation validation is approved. Production release
admission is **not approved** for this delta until the blocked runtime,
tenant-isolation, upgrade, browser, and recovery gates pass against one
immutable image digest.

