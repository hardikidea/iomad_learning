# AWS Architecture

Terraform provisions environment-specific stacks for dev, stage, and production:

- ECS web service
- ECS cron service
- one-off ECS migration task
- ALB and target groups
- RDS PostgreSQL
- EFS for `iomaddata`
- Redis
- Secrets Manager
- Route53 and ACM certificates
- CloudWatch logs, alarms, and backup policies

The application image is immutable and commit-addressed. Database migrations run once through a controlled task while cron is paused.
