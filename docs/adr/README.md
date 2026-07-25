# Architecture Decision Records

This directory records significant architecture decisions for IOMAD Learning.

## Index

| ADR | Title | Status | Date |
| --- | --- | --- | --- |
| [0001](0001-aws-iomad-target-architecture.md) | AWS IOMAD target architecture | Accepted | 2026-06-28 |
| [0002](0002-github-actions-oidc-ghcr-delivery.md) | GitHub Actions OIDC and GHCR delivery | Accepted | 2026-06-28 |
| [0003](0003-operability-and-gamification-boundaries.md) | Operability and gamification boundaries | Accepted | 2026-07-25 |
| [Legacy 001](001-product-suite-boundaries.md) | Product suite boundaries | Accepted | 2026-07-25 |

## Creating A New ADR

1. Copy [template.md](template.md) to `NNNN-short-title.md`.
2. Fill in context, decision drivers, options, decision, and consequences.
3. Submit a PR with the related Terraform, workflow, or operational change.
4. Update this index after the ADR is accepted.

## Status Values

- `Proposed`: under review.
- `Accepted`: decision is approved and current.
- `Deprecated`: decision is no longer recommended.
- `Superseded`: replaced by another ADR.
- `Rejected`: considered and not adopted.
