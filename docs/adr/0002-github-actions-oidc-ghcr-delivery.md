# ADR-0002: GitHub Actions OIDC And GHCR Delivery

## Status

Accepted

## Context

The project needs CI/CD for IOMAD source validation, Docker image publishing, Terraform plan/apply, upgrade workflows, backup workflows, and restore workflows. The user explicitly required OIDC-based AWS access and no SSH connection path.

## Decision Drivers

- Security: avoid long-lived AWS access keys and SSH keys.
- Traceability: every deployment should tie to a GitHub run, commit SHA, and image tag.
- Supply chain: images should be scanned and published from CI.
- Simplicity: use one platform for source, pipeline, packages, and approvals.

## Considered Options

### GitHub Actions OIDC And GHCR

- Pros: short-lived AWS credentials, native environment approvals, built-in package registry, direct PR checks.
- Cons: GHCR private packages need an ECS registry credential secret.

### AWS CodePipeline And ECR

- Pros: native AWS deployment tooling and ECR integration.
- Cons: more moving parts for GitHub-centric development and the project chose GHCR.

### Self-Hosted Runner With SSH

- Pros: direct server control.
- Cons: violates the no-SSH requirement and increases secret and host maintenance risk.

## Decision

Use GitHub Actions with AWS OIDC roles for infrastructure and server operations, and publish IOMAD images to GHCR.

## Rationale

GitHub Actions OIDC provides short-lived AWS access per environment while GitHub Environments provide review gates. GHCR keeps image publishing close to the source repository and supports immutable commit-based image tags.

## Consequences

### Positive

- No AWS keys are stored in the repository.
- No SSH deployment path is required.
- CI gates, approvals, package publishing, Terraform, backup, restore, and upgrade workflows are visible in one place.

### Negative

- GHCR private images require Secrets Manager registry credentials for ECS.
- AWS role permissions must be reviewed carefully as the Terraform footprint grows.

### Risks And Mitigations

- Risk: Renovate PRs using `GITHUB_TOKEN` may not trigger downstream workflows.
- Mitigation: configure `RENOVATE_TOKEN`.
- Risk: Terraform drift happens outside CI.
- Mitigation: run scheduled refresh-only drift detection.

## Implementation Notes

- Workflow: `.github/workflows/ci_cd_pipeline.yml`.
- Drift workflow: `.github/workflows/infrastructure_drift_detection.yml`.
- Bootstrap roles: `terraform/bootstrap`.
- Pipeline guide: [Pipeline onboarding](../pipeline-onboarding.md).
