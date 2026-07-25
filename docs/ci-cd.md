# CI/CD

PR checks are expected to cover:

- ShellCheck and shell syntax
- pinned-source operational baseline and tracked-hotfix checksum validation
- tracked override compatibility declarations
- PHP syntax and coding standards
- PHPUnit for supported local plugins
- institution-pack validation
- clean install with both sanitized demo imports and tenant-host smoke tests
- previous-reviewed-commit upgrade test
- matching database and dataroot backup/restore drill
- Docker build
- Trivy secret scan
- Composer dependency audit
- CycloneDX SBOM generation and artifact upload
- Trivy filesystem, IaC, and image scans
- Terraform validation and plan
- checksummed local backup/restore acceptance

The required PHPUnit job builds and installs the pinned local stack, initializes Moodle's isolated `phpu_` database and dataroot, runs `local_institutionpack` tenant-isolation tests, uploads JUnit, and removes test state. The optional Behat job remains a declared placeholder until browser acceptance infrastructure is implemented.

The Composer audit uses `iomad/composer.json` plus the checksum-guarded
deployable `iomad-overrides/composer.lock`. This keeps the ignored upstream
checkout unchanged while ensuring CI and PHPUnit consume the reviewed patched
development dependency set.

Production images are built from `IOMAD_COMMIT` plus tracked `iomad-overrides/`, then published to GHCR with commit-addressed tags. Do not publish or deploy `latest`.

Deployment order:

1. Build immutable image.
2. Deploy dev automatically.
3. Promote to stage with approval.
4. Promote to production with approval.
5. Run one controlled ECS migration task.
6. Smoke-test default URL and configured tenant hostnames.

The manual `IOMAD Version Upgrade` workflow requires `UPGRADE <environment> <commit-sha>` and validates that the SHA exists upstream before building.
