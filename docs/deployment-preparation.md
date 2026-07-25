# Deployment Preparation Guide

This guide lists everything needed to deploy IOMAD Learning IOMAD to AWS from a fresh GitHub repository. It is written for a new operator who has not deployed this project before.

## 1. What You Are Deploying

The production-style AWS setup creates one IOMAD environment per Terraform root:

- `dev`
- `stage`
- `prod`

Each environment uses:

- Route53 DNS record, when configured.
- Public Application Load Balancer.
- ECS Fargate IOMAD web service.
- ECS Fargate IOMAD cron service.
- RDS PostgreSQL database.
- EFS file system for `iomaddata`.
- ElastiCache Redis.
- Secrets Manager for generated database and IOMAD admin passwords.
- CloudWatch logs and alarms.

The CI/CD pipeline uses GitHub Actions with AWS OIDC. No SSH key is required.

## 2. Required Accounts And Access

| Item | Required | Notes |
| --- | --- | --- |
| AWS account | Yes | Use a dedicated AWS account for this project when possible. |
| AWS admin access for bootstrap | Yes | Needed only to create Terraform state, GitHub OIDC roles, IAM roles, and environment infrastructure. |
| GitHub repository | Yes | Example: `YOUR_ORG/iomad_learning`. |
| GitHub admin access | Yes | Needed to create repository variables, secrets, environments, and approvals. |
| Domain name | Recommended | Required for friendly production URL and HTTPS. |
| Route53 hosted zone | Recommended | Terraform can create IOMAD DNS records when a hosted zone exists. |
| ACM certificate | Recommended for shared envs, required for production HTTPS | Must be in the same AWS region as the ALB. |
| GHCR package access | Yes | Images are published to GitHub Container Registry. |
| Email/SMTP provider | Production recommended | Local uses MailPit. Production should use a real SMTP provider such as Amazon SES or another approved service. |

## 3. Information To Collect Before Starting

Fill this table before deployment.

| Field | Example | Your Value |
| --- | --- | --- |
| AWS account ID | `123456789012` |  |
| AWS region | `us-west-2` |  |
| Project name | `iomad-learning` |  |
| GitHub repository | `YOUR_ORG/iomad_learning` |  |
| GHCR image name | `ghcr.io/hardikidea/iomadlearning` |  |
| Domain root | `example.com` |  |
| Dev IOMAD URL | `iomad-dev.example.com` |  |
| Stage IOMAD URL | `iomad-stage.example.com` |  |
| Prod IOMAD URL | `iomad.example.com` |  |
| Route53 hosted zone ID | `Z1234567890ABC` |  |
| ACM certificate ARN | `arn:aws:acm:us-west-2:123456789012:certificate/...` |  |
| Alarm SNS topic ARN | `arn:aws:sns:us-west-2:123456789012:iomad-learning-prod-alerts` |  |
| Backup vault name | `iomad-learning-backups` |  |
| AWS Backup role ARN | `arn:aws:iam::123456789012:role/service-role/AWSBackupDefaultServiceRole` |  |
| Production admin email | `admin@example.com` |  |
| SMTP host | `email-smtp.us-west-2.amazonaws.com` |  |

## 4. Local Workstation Requirements

Install these tools on the machine used for bootstrap and validation:

- Git.
- Docker Desktop or Docker Engine with Compose v2.
- Terraform 1.6 or newer.
- AWS CLI v2.
- Node.js 18+ and pnpm if you want local Husky hooks.
- A shell environment that can run Bash scripts.

Check versions:

```bash
git --version
docker version
docker compose version
terraform version
aws --version
```

Clone the repository:

```bash
git clone git@github.com:YOUR_ORG/iomad_learning.git
cd iomad_learning
```

Install local hooks:

```bash
pnpm install
```

Run local static validation:

```bash
./scripts/validate-docs.sh
docker compose config --quiet
terraform fmt -check -recursive terraform
```

## 5. AWS Account Preparation

### 5.1 Secure The AWS Account

Before creating infrastructure:

- Enable MFA for the AWS root user.
- Do not use root for normal work.
- Use an admin IAM Identity Center permission set or an admin IAM role for bootstrap.
- Confirm billing alerts and budget alerts are configured.
- Confirm the selected AWS region is approved for the project.

### 5.2 Configure AWS CLI For Bootstrap

Authenticate locally using your approved AWS admin method, then verify identity:

```bash
aws sts get-caller-identity
```

The account ID must match the account you plan to deploy into.

### 5.3 Choose Network CIDRs

Defaults are already configured:

| Environment | VPC CIDR |
| --- | --- |
| Dev | `10.40.0.0/16` |
| Stage | `10.41.0.0/16` |
| Prod | `10.42.0.0/16` |

Change these in `terraform/envs/<env>/variables.tf` only if they conflict with existing AWS networks, VPNs, or peering.

## 6. Domain And TLS Preparation

### 6.1 Decide DNS Names

Recommended names:

```text
dev:   iomad-dev.example.com
stage: iomad-stage.example.com
prod:  iomad.example.com
```

### 6.2 Route53 Hosted Zone

Use one of these options:

- Existing hosted zone: collect the hosted zone ID.
- New hosted zone: create it in Route53, then update the domain registrar with the Route53 name servers.

Do not continue production deployment until public DNS delegation is working.

### 6.3 ACM Certificate

Create or request an ACM certificate for the IOMAD hostnames in the same AWS region used by Terraform.

Suggested certificate names:

```text
iomad-dev.example.com
iomad-stage.example.com
iomad.example.com
```

or one wildcard:

```text
*.example.com
```

Use DNS validation. Wait until the certificate status is `Issued`.

Record the certificate ARN. It is used as `certificate_arn` in environment tfvars.

## 7. GitHub Repository Setup

### 7.1 Repository Variables

In GitHub:

1. Open the repository.
2. Go to `Settings`.
3. Go to `Secrets and variables`.
4. Select `Actions`.
5. Add these repository variables:

```text
AWS_ACCOUNT_ID=<your AWS account id>
AWS_REGION=us-west-2
GHCR_IMAGE_NAME=ghcr.io/hardikidea/iomadlearning
BACKUP_VAULT_NAME=<aws-backup-vault-name>
BACKUP_ROLE_ARN=<aws-backup-service-role-arn>
```

### 7.2 Repository Secrets

Add this repository secret:

```text
RENOVATE_TOKEN=<github app token or fine-grained token>
```

The token should allow Renovate to create branches and PRs. Without it, Renovate may run but its PRs may not trigger the full downstream workflow.

### 7.3 GitHub Environments

Create these GitHub Environments:

```text
dev
stage
prod
prod-approval
```

Recommended protection:

| Environment | Protection |
| --- | --- |
| `dev` | Optional approval. |
| `stage` | Required reviewer from engineering/admin team. |
| `prod` | Required reviewer from release owner. |
| `prod-approval` | Strict approval before production apply. |

## 8. GHCR Image Access

The pipeline publishes images to:

```text
ghcr.io/hardikidea/iomadlearning
```

### Option A: Public GHCR Package

If the GHCR package is public, ECS can pull the image without registry credentials. Leave this Terraform variable empty:

```hcl
container_registry_credentials_secret_arn = ""
```

### Option B: Private GHCR Package

If the GHCR package is private:

1. Create a GitHub token with package read access.
2. Store it in AWS Secrets Manager as JSON:

   ```json
   {"username":"hardikidea","password":"<github-token-with-read-packages>"}
   ```

3. Copy the secret ARN.
4. Set it in each environment tfvars:

   ```hcl
   container_registry_credentials_secret_arn = "arn:aws:secretsmanager:us-west-2:123456789012:secret:iomad-learning/prod/ghcr-xxxxxx"
   ```

## 9. Backup And Alert Preparation

### 9.1 CloudWatch Alarm Notifications

Create an SNS topic for alerts, for example:

```text
iomad-learning-prod-alerts
```

Subscribe the operations email or incident channel. Confirm the subscription.

Set the topic ARN in environment tfvars:

```hcl
alarm_sns_topic_arns = ["arn:aws:sns:us-west-2:123456789012:iomad-learning-prod-alerts"]
```

### 9.2 AWS Backup

The manual backup and upgrade workflows need:

- Backup vault name.
- AWS Backup role ARN.

If using the AWS managed default role, record its ARN. If using a project-specific role, confirm it can back up EFS.

Save these in GitHub repository variables:

```text
BACKUP_VAULT_NAME=<backup-vault-name>
BACKUP_ROLE_ARN=<backup-role-arn>
```

## 10. Terraform Bootstrap

Bootstrap creates:

- S3 bucket for Terraform state.
- DynamoDB lock table.
- KMS key for state encryption.
- GitHub OIDC provider.
- GitHub Actions roles for dev, stage, and prod.

Run once per AWS account/region:

```bash
cd terraform/bootstrap
cp terraform.tfvars.example terraform.tfvars
```

Edit `terraform.tfvars`:

```hcl
aws_region        = "us-west-2"
project_name      = "iomad-learning"
github_repository = "YOUR_ORG/iomad_learning"
```

Apply:

```bash
terraform init
terraform plan
terraform apply
```

Return to repository root:

```bash
cd ../..
```

After bootstrap, the pipeline can assume these roles:

```text
iomad-learning-dev-github-actions
iomad-learning-stage-github-actions
iomad-learning-prod-github-actions
```

## 11. Environment Terraform Configuration

Each environment has an example tfvars file:

```text
terraform/envs/dev/terraform.tfvars.example
terraform/envs/stage/terraform.tfvars.example
terraform/envs/prod/terraform.tfvars.example
```

Create real local tfvars only on your machine. Do not commit `terraform.tfvars`.

Example prod values:

```hcl
image_tag = "replace-with-image-tag"

container_repository_url = "ghcr.io/hardikidea/iomadlearning"

route53_zone_id     = "Z1234567890ABC"
route53_record_name = "iomad.example.com"
certificate_arn     = "arn:aws:acm:us-west-2:123456789012:certificate/..."

alarm_sns_topic_arns = ["arn:aws:sns:us-west-2:123456789012:iomad-learning-prod-alerts"]

autoscaling_min_capacity  = 2
autoscaling_max_capacity  = 8
autoscaling_cpu_target    = 65
autoscaling_memory_target = 70
```

For production, keep these defaults unless you intentionally change them:

```hcl
database_deletion_protection          = true
database_skip_final_snapshot          = false
database_multi_az                     = true
database_performance_insights_enabled = true
```

## 12. First CI/CD Run

### 12.1 Validate Pull Request

Open a PR and let the `IOMAD Delivery Pipeline` run. It should pass:

- IOMAD source integrity.
- Static quality.
- Documentation validation.
- Supply-chain security.
- Docker image build and scan.
- Local IOMAD smoke test.
- Terraform plans.

### 12.2 Publish Image And Deploy Dev

After merge to `main`, the pipeline can publish the GHCR image and deploy based on the workflow rules.

For a manual deploy:

1. Open GitHub Actions.
2. Select `IOMAD Delivery Pipeline`.
3. Click `Run workflow`.
4. Use:

   ```text
   publish_image=true
   apply_infrastructure=true
   target_environment=dev
   run_extended_tests=false
   ```

5. Wait for dev apply and ECS service stabilization.

### 12.3 Deploy Stage

Run the same workflow with:

```text
target_environment=stage
```

Confirm:

- Stage ECS services are stable.
- Stage smoke validation passes.
- Browser validation passes when an `e2e/` suite exists.

### 12.4 Deploy Production

Run or allow the pipeline to continue to prod only after stage is healthy.

Production requires:

- `prod-approval` environment approval.
- Prod Terraform apply.
- Prod ECS web stabilization.
- Prod ECS cron stabilization.
- Production smoke validation.

## 13. First IOMAD Database Install On ECS

Terraform creates infrastructure and starts containers, but the first IOMAD database install must be run once per environment.

Set variables:

```bash
export ENVIRONMENT=dev
export PROJECT_NAME=iomad-learning
export AWS_REGION=us-west-2
export CLUSTER="${PROJECT_NAME}-${ENVIRONMENT}-cluster"
export SERVICE="${PROJECT_NAME}-${ENVIRONMENT}-service"
```

Find a running IOMAD task:

```bash
export TASK_ARN="$(
  aws ecs list-tasks \
    --cluster "${CLUSTER}" \
    --service-name "${SERVICE}" \
    --desired-status RUNNING \
    --query 'taskArns[0]' \
    --output text
)"
```

Run IOMAD install:

```bash
aws ecs execute-command \
  --cluster "${CLUSTER}" \
  --task "${TASK_ARN}" \
  --container iomad \
  --interactive \
  --command "sh -lc 'php admin/cli/install_database.php --agree-license --adminuser=\"\$IOMAD_ADMIN_USER\" --adminpass=\"\$IOMAD_ADMIN_PASSWORD\" --adminemail=\"\$IOMAD_ADMIN_EMAIL\" --fullname=\"\$IOMAD_SITE_FULLNAME\" --shortname=\"\$IOMAD_SITE_SHORTNAME\"'"
```

Purge caches:

```bash
aws ecs execute-command \
  --cluster "${CLUSTER}" \
  --task "${TASK_ARN}" \
  --container iomad \
  --interactive \
  --command 'php admin/cli/purge_caches.php'
```

Repeat for `stage` and `prod`.

## 14. Get Initial IOMAD Admin Password

The generated admin password is stored in AWS Secrets Manager.

Get the secret ARN from Terraform:

```bash
terraform -chdir=terraform/envs/dev output iomad_secret_arn
```

Read the secret:

```bash
aws secretsmanager get-secret-value \
  --secret-id "<iomad-secret-arn>" \
  --query SecretString \
  --output text
```

The JSON contains:

```json
{
  "POSTGRES_PASSWORD": "...",
  "IOMAD_ADMIN_PASSWORD": "..."
}
```

Login with:

```text
Username: admin
Password: <IOMAD_ADMIN_PASSWORD>
```

Change the admin email and password according to the school policy after first login.

## 15. Production Email Setup

Local Docker uses MailPit. Production should use a real SMTP provider.

Minimum information needed:

| Field | Example |
| --- | --- |
| SMTP host | `email-smtp.us-west-2.amazonaws.com` |
| SMTP port | `587` |
| SMTP security | `STARTTLS` |
| SMTP username | Provider-generated username |
| SMTP password | Provider-generated password |
| No-reply address | `noreply@example.com` |
| Support email | `support@example.com` |

Store SMTP credentials in Secrets Manager or another approved secret store before wiring them into IOMAD. Do not commit SMTP credentials.

After configuring SMTP in IOMAD, send a test message and confirm delivery.

## 16. Post-Deployment Verification

Run these checks for each environment.

### 16.1 ECS Services

```bash
aws ecs describe-services \
  --cluster "${PROJECT_NAME}-${ENVIRONMENT}-cluster" \
  --services "${PROJECT_NAME}-${ENVIRONMENT}-service" "${PROJECT_NAME}-${ENVIRONMENT}-cron" \
  --query 'services[].{name:serviceName,status:status,desired:desiredCount,running:runningCount,taskDefinition:taskDefinition}' \
  --output table
```

### 16.2 Public URL

```bash
curl -fsS "https://iomad.example.com/login/index.php" >/tmp/iomad-login.html
```

Use the correct URL for `dev`, `stage`, or `prod`.

### 16.3 IOMAD CLI Health

```bash
aws ecs execute-command \
  --cluster "${CLUSTER}" \
  --task "${TASK_ARN}" \
  --container iomad \
  --interactive \
  --command 'php admin/cli/cfg.php --name=release'
```

```bash
aws ecs execute-command \
  --cluster "${CLUSTER}" \
  --task "${TASK_ARN}" \
  --container iomad \
  --interactive \
  --command 'php admin/cli/checks.php'
```

### 16.4 CloudWatch Alarms

```bash
aws cloudwatch describe-alarms \
  --alarm-names $(terraform -chdir="terraform/envs/${ENVIRONMENT}" output -json cloudwatch_alarm_names | jq -r '.[]') \
  --query 'MetricAlarms[].{name:AlarmName,state:StateValue,reason:StateReason}' \
  --output table
```

All alarms should be `OK` after the environment settles.

### 16.5 Functional Checks

Manually verify:

- Login page loads.
- Admin login works.
- Dashboard loads.
- My courses page loads.
- Course category page loads.
- Course view page loads.
- File upload works.
- Cron is running.
- Email test works.

## 17. Handoff Checklist

Before marking deployment complete, record:

| Item | Recorded |
| --- | --- |
| AWS account ID |  |
| AWS region |  |
| GitHub repository URL |  |
| GHCR image URL |  |
| Dev URL |  |
| Stage URL |  |
| Prod URL |  |
| Route53 hosted zone ID |  |
| ACM certificate ARN |  |
| Backup vault name |  |
| Backup role ARN |  |
| Alarm SNS topic ARN |  |
| First deployed image tag |  |
| Terraform bootstrap applied date |  |
| Dev install completed |  |
| Stage install completed |  |
| Prod install completed |  |
| Admin password rotated |  |
| SMTP verified |  |
| Backup workflow tested |  |
| Restore workflow tested in dev or stage |  |
| Upgrade workflow tested in dev or stage |  |

## 18. Common Beginner Mistakes

| Problem | Fix |
| --- | --- |
| GitHub Actions cannot assume AWS role | Confirm bootstrap `github_repository` exactly matches `owner/repo`, and repository variables are set. |
| Terraform backend bucket not found | Run `terraform/bootstrap` first. |
| ALB URL works but custom domain does not | Confirm Route53 hosted zone ID, DNS delegation, and record name. |
| HTTPS listener is not created | Set `certificate_arn` and confirm ACM certificate is `Issued`. |
| ECS cannot pull GHCR image | Make package public or configure `container_registry_credentials_secret_arn`. |
| Production smoke fails after deploy | Check ECS service events, CloudWatch logs, ALB target health, and IOMAD logs before running another apply. |
| Admin password is unknown | Read the IOMAD Secrets Manager secret. |
| Email does not send | Configure production SMTP; MailPit is local-only. |
| Backup workflow fails for EFS | Confirm `BACKUP_VAULT_NAME`, `BACKUP_ROLE_ARN`, and role permissions. |
| Terraform detects drift | Use the drift workflow summary; encode intentional changes in Terraform or revert accidental AWS changes. |

## 19. Deployment Complete Criteria

Deployment is complete only when:

- Dev, stage, and prod infrastructure are applied from Terraform.
- IOMAD database install is complete for each active environment.
- Route53 and HTTPS work for shared environments.
- Production smoke validation passes.
- CloudWatch alarms are connected to a real notification channel.
- Backup workflow has completed successfully.
- Restore workflow has been tested in dev or stage.
- Initial IOMAD admin password has been rotated.
- Documentation handoff table is filled.
