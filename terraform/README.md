# Terraform Infrastructure

This folder contains AWS Terraform for IOMAD dev, stage, and prod deployments. It follows the CourseCloud reference structure at a high level: a bootstrap layer, environment roots, reusable modules, and GitHub Actions plan/apply jobs.

No SSH keys are used. GitHub Actions authenticates to AWS through OIDC and short-lived STS credentials.

## Layout

| Path | Purpose |
| --- | --- |
| `bootstrap/` | Creates shared CI/CD resources: Terraform state bucket, DynamoDB lock table, GitHub OIDC provider, and dev/stage/prod GitHub Actions IAM roles. |
| `envs/dev/` | Dev IOMAD environment. |
| `envs/stage/` | Stage IOMAD environment. |
| `envs/prod/` | Prod IOMAD environment. |
| `modules/iomad_environment/` | Reusable IOMAD AWS stack module. |
| `modules/route53/` | Reusable Route53 ALB alias record module. |
| `local/floci-provider.tf.example` | Local-only AWS provider endpoints for Floci. |

## Resources Per Environment

Each environment creates:

- VPC, public subnets, private subnets, route tables, internet gateway, and NAT gateway.
- Public Application Load Balancer.
- ECS Fargate cluster.
- IOMAD web ECS service.
- IOMAD cron ECS service.
- RDS PostgreSQL 16 with encrypted storage, automated backups, optional Multi-AZ, optional Performance Insights, and final snapshot protection outside dev.
- EFS file system, access point, lifecycle policy, and backup policy for `iomaddata`.
- ElastiCache Redis.
- Secrets Manager secret for generated database and IOMAD admin passwords.
- CloudWatch log group.
- CloudWatch alarms for ALB unhealthy targets, ALB 5xx, ECS CPU/memory, RDS CPU/storage, and EFS I/O.
- IAM task execution and task roles.
- Optional Route53 A and AAAA alias records for the environment ALB.
- Security groups scoped between ALB, ECS, RDS, Redis, and EFS.

## Bootstrap Once

Run bootstrap from a workstation or admin automation that already has AWS permissions to create IAM, S3, and DynamoDB resources.

```bash
cd terraform/bootstrap
cp terraform.tfvars.example terraform.tfvars
```

Edit `terraform.tfvars`:

```hcl
aws_region        = "us-west-2"
project_name      = "iomad-learning"
github_repository = "your-github-org/your-repository"
```

Apply:

```bash
terraform init
terraform plan
terraform apply
```

Bootstrap creates predictable OIDC role names:

```text
iomad-learning-dev-github-actions
iomad-learning-stage-github-actions
iomad-learning-prod-github-actions
```

The same roles are used by the manual `Server Backup` and `Server Restore` GitHub Actions workflows. Re-apply the bootstrap layer after changing this repository so the roles include AWS Backup permissions for EFS recovery-point jobs.

The `cron_desired_count` variable exists for the manual `IOMAD Version Upgrade` workflow. Normal plans use the default `1`; the upgrade workflow temporarily applies `0` while IOMAD database upgrade runs.

Stage and prod default to RDS Multi-AZ and Performance Insights. Dev keeps cheaper database defaults and skips final RDS snapshots on destroy. Shared environments keep final snapshots enabled and should keep deletion protection enabled.

CloudWatch alarms are created by default. To send alarm and OK notifications, set `alarm_sns_topic_arns` in the environment tfvars file:

```hcl
alarm_sns_topic_arns = ["arn:aws:sns:us-west-2:123456789012:iomad-learning-prod-alerts"]
```

Tune web autoscaling per environment when needed:

```hcl
autoscaling_min_capacity  = 2
autoscaling_max_capacity  = 8
autoscaling_cpu_target    = 65
autoscaling_memory_target = 70
```

## GitHub Repository Setup

Create GitHub repository variables:

```text
AWS_ACCOUNT_ID=<your AWS account id>
AWS_REGION=us-west-2
```

Create GitHub Environments:

```text
dev
stage
prod
```

Use GitHub environment protection rules for `stage` and `prod` approvals.

## Local Terraform Commands

The CI pipeline supplies backend config dynamically. For local commands, use the same backend config pattern:

```bash
export AWS_ACCOUNT_ID=123456789012
export AWS_REGION=us-west-2
export PROJECT_NAME=iomad-learning

terraform -chdir=terraform/envs/dev init \
  -backend-config="bucket=${PROJECT_NAME}-${AWS_ACCOUNT_ID}-${AWS_REGION}-tfstate" \
  -backend-config="dynamodb_table=${PROJECT_NAME}-terraform-locks" \
  -backend-config="key=${PROJECT_NAME}/dev.tfstate" \
  -backend-config="region=${AWS_REGION}" \
  -backend-config="encrypt=true"

terraform -chdir=terraform/envs/dev plan \
  -var "container_repository_url=ghcr.io/hardikidea/iomadlearning" \
  -var "image_tag=<published-image-tag>"
```

Replace `dev` with `stage` or `prod` for other environments.

## Drift Detection

The [Infrastructure Drift Detection](../.github/workflows/infrastructure_drift_detection.yml) workflow runs refresh-only plans for dev, stage, and prod. It uses the same OIDC roles and backend configuration as the delivery pipeline.

Run the same check locally for one environment:

```bash
terraform -chdir=terraform/envs/prod plan \
  -refresh-only \
  -detailed-exitcode \
  -input=false
```

Exit code `0` means no drift. Exit code `2` means Terraform detected remote resource changes compared with state. Encode intentional drift in Terraform through a PR or revert accidental drift in AWS.

## Route53 DNS

Each environment can create a Route53 alias record for its ALB. DNS is disabled by default so plans work before a hosted zone or certificate exists.

Set these variables in the target environment tfvars file:

```hcl
route53_zone_id     = "Z1234567890ABC"
route53_record_name = "iomad-dev.example.com"
certificate_arn     = "arn:aws:acm:us-west-2:123456789012:certificate/..."
```

Recommended names:

```text
dev:   iomad-dev.example.com
stage: iomad-stage.example.com
prod:  iomad.example.com
```

When `iomad_wwwroot` is not set manually, the environment root derives it from `route53_record_name`. If `certificate_arn` is set, IOMAD uses `https://<record-name>`; otherwise it uses `http://<record-name>`.

To also create an IPv6 AAAA alias record:

```hcl
route53_create_ipv6_record = true
```

## First IOMAD Database Install On ECS

Terraform creates infrastructure and deploys the image. The first IOMAD database installation should be run once after the first ECS deployment. This uses ECS Exec through AWS APIs, not SSH.

Example flow:

```bash
aws ecs list-tasks \
  --cluster iomad-learning-dev-cluster \
  --service-name iomad-learning-dev-service

aws ecs execute-command \
  --cluster iomad-learning-dev-cluster \
  --task <task-arn> \
  --container iomad \
  --interactive \
  --command "sh -lc 'php admin/cli/install_database.php --agree-license --adminuser=\"\$IOMAD_ADMIN_USER\" --adminpass=\"\$IOMAD_ADMIN_PASSWORD\" --adminemail=\"\$IOMAD_ADMIN_EMAIL\" --fullname=\"\$IOMAD_SITE_FULLNAME\" --shortname=\"\$IOMAD_SITE_SHORTNAME\"'"
```

After installation, purge caches:

```bash
aws ecs execute-command \
  --cluster iomad-learning-dev-cluster \
  --task <task-arn> \
  --container iomad \
  --interactive \
  --command 'php admin/cli/purge_caches.php'
```

## Notes

- The production Docker image is built with `INCLUDE_IOMAD_SOURCE=true`, so it bakes the reviewed `IOMAD_COMMIT` into the image.
- Local Docker builds the exact pinned IOMAD source and tracked overrides into
  the application image. It does not bind-mount `./iomad` into the web or cron
  service.
- Prod enables deletion protection on RDS by default.
- State files and local `*.tfvars` are ignored by Git.
- Floci is a development emulator, not a production Terraform backend. See
  [the local cloud runbook](../docs/local-cloud-floci.md).
