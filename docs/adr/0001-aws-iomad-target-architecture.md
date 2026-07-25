# ADR-0001: AWS IOMAD Target Architecture

## Status

Accepted

## Context

IOMAD requires a horizontally scalable PHP web tier, a transactional database, shared `iomaddata`, cron processing, cache support, and reliable upgrade and rollback operations. The project also needs three environments: dev, stage, and prod.

## Decision Drivers

- Reliability: web tasks must scale without losing uploaded files or cache consistency.
- Security: data services must stay private and secrets must not live in GitHub.
- Performance: IOMAD needs database, cache, and file-system resources sized independently.
- Operations: backup, restore, upgrade, and smoke validation must work without SSH access.
- Cost: dev should stay lightweight while stage/prod model production behavior.

## Considered Options

### ECS Fargate, ALB, RDS PostgreSQL, EFS, Redis

- Pros: managed compute, private data tier, native autoscaling, ECS Exec for controlled CLI operations, good Terraform support.
- Cons: IOMAD schema upgrades still require careful sequencing, and EFS performance must be monitored.

### EC2 Auto Scaling Group

- Pros: full host control and familiar VM operations.
- Cons: patching burden, AMI lifecycle complexity, and more operational work for scaling and deployments.

### Single VM

- Pros: simplest initial deployment.
- Cons: poor availability, poor scaling, and high recovery risk.

## Decision

Use ECS Fargate behind an Application Load Balancer for IOMAD web tasks, a separate ECS cron service, RDS PostgreSQL for the database, EFS for `iomaddata`, ElastiCache Redis for caching, Secrets Manager for runtime secrets, Route53 for DNS, and CloudWatch for logs and alarms.

## Rationale

This architecture maps directly to IOMAD's state boundaries: code is immutable in the image, uploaded files live on EFS, transactional data lives in RDS, and cron is isolated from web traffic. It gives the project a managed, repeatable path for dev, stage, and prod without SSH.

## Consequences

### Positive

- Web capacity can scale independently from database and file storage.
- `iomaddata` is shared across all web tasks.
- Cron can be paused during upgrades without stopping web service definitions.
- OIDC-based CI/CD can plan and apply infrastructure consistently.

### Negative

- EFS and RDS need explicit backup and restore runbooks.
- Database schema upgrades cannot be rolled back by image rollback alone.
- Blue/green deployment would require additional CodeDeploy target group wiring.

### Risks And Mitigations

- Risk: an upgrade changes schema and uploaded files.
- Mitigation: capture RDS and EFS backups together and restore them together.
- Risk: EFS I/O limits cause slow pages.
- Mitigation: use elastic throughput and CloudWatch `PercentIOLimit` alarms.

## Implementation Notes

- Terraform module: `terraform/modules/iomad_environment`.
- Operations: [AWS architecture blueprint](../aws-architecture.md) and [upgrade runbook](../upgrade-backup-restore.md).
- Pipeline: [CI/CD documentation](../ci-cd.md).
