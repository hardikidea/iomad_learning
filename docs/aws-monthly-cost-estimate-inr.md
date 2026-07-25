# AWS Monthly Cost Estimate In INR

Estimate date: 2026-06-28

This document estimates one month of AWS running cost for IOMAD Learning IOMAD. Use it for planning and sales discussion only. Before committing to a school, validate the final quote in [AWS Pricing Calculator](https://calculator.aws/#/) with the actual AWS region, traffic, storage, backups, and support plan.

## Important Assumptions

| Item | Assumption |
| --- | --- |
| AWS region | `us-west-2`, because this repository defaults to `us-west-2`. |
| Month length | 730 hours. |
| Currency conversion | `1 USD = INR 94.38`, checked from public USD-INR converter references on 2026-06-28. |
| Tax | AWS taxes/GST are not included in base AWS cost. Sales pricing examples show GST separately where useful. |
| NAT Gateway | Estimated at `USD 0.045/hour` plus `USD 0.045/GB` data processed. Confirm in AWS Pricing Calculator. |
| Data transfer | Small outbound usage assumed. Actual cost changes with videos, downloads, backups, and region. |
| EFS lifecycle | Standard environments assume some `iomaddata` moves to EFS Infrequent Access. |
| Support plan | AWS Support plan cost is not included. Add it separately if using Business or Enterprise Support. |
| Production email | SES or SMTP provider cost is not included. |

## Reference AWS Rates Used

Rates were checked against AWS public pricing references and AWS public pricing files for `us-west-2`.

| Service | Reference Rate Used |
| --- | --- |
| ECS Fargate Linux x86 vCPU | `USD 0.04048 / vCPU-hour` |
| ECS Fargate Linux x86 memory | `USD 0.004445 / GB-hour` |
| RDS PostgreSQL `db.t4g.micro` Single-AZ | `USD 0.016 / hour` |
| RDS PostgreSQL `db.t4g.small` Multi-AZ | `USD 0.065 / hour` |
| RDS PostgreSQL `db.t4g.medium` Multi-AZ | `USD 0.129 / hour` |
| RDS PostgreSQL gp2 Single-AZ storage | `USD 0.115 / GB-month` |
| RDS PostgreSQL gp3 Multi-AZ storage | `USD 0.23 / GB-month` |
| EFS Standard storage | `USD 0.30 / GB-month` |
| EFS Infrequent Access storage | `USD 0.025 / GB-month` |
| Application Load Balancer | `USD 0.0225 / hour` |
| ALB LCU | `USD 0.008 / LCU-hour` |
| Redis `cache.t4g.micro` | `USD 0.016 / hour` |
| Redis `cache.t4g.small` | `USD 0.032 / hour` estimate. Confirm in calculator. |
| CloudWatch Logs ingestion | `USD 0.50 / GB` |
| CloudWatch Logs storage | `USD 0.03 / GB-month` |

Pricing references:

- [AWS Fargate pricing](https://aws.amazon.com/fargate/pricing/)
- [Amazon RDS for PostgreSQL pricing](https://aws.amazon.com/rds/postgresql/pricing/)
- [Amazon EFS pricing](https://aws.amazon.com/efs/pricing/)
- [Elastic Load Balancing pricing](https://aws.amazon.com/elasticloadbalancing/pricing/)
- [Amazon VPC pricing](https://aws.amazon.com/vpc/pricing/)
- [Amazon ElastiCache pricing](https://aws.amazon.com/elasticache/pricing/)
- [Amazon CloudWatch pricing](https://aws.amazon.com/cloudwatch/pricing/)
- [AWS Pricing Calculator](https://calculator.aws/#/)
- [XE USD to INR converter](https://www.xe.com/currencyconverter/convert/?Amount=1&From=USD&To=INR)

## Scenario 1: Pilot Or Small Single Environment

Use for demos, small pilots, or non-critical school trials. This is not the recommended high-availability production setup.

Assumed capacity:

- 1 IOMAD web task: `1 vCPU`, `2 GB`.
- 1 IOMAD cron task: `0.5 vCPU`, `1 GB`.
- RDS PostgreSQL `db.t4g.micro`, Single-AZ.
- 50 GB RDS storage.
- 50 GB EFS data.
- Redis `cache.t4g.micro`, 1 node.
- 1 ALB, 1 LCU.
- 20 GB NAT data processing.
- 10 GB CloudWatch log ingestion.

| Cost Area | Monthly USD | Approx INR |
| --- | ---: | ---: |
| Fargate web and cron | 54.06 | 5,102 |
| RDS PostgreSQL | 17.43 | 1,645 |
| EFS `iomaddata` | 15.00 | 1,416 |
| Redis | 11.68 | 1,102 |
| ALB | 22.27 | 2,101 |
| NAT Gateway | 33.75 | 3,185 |
| CloudWatch | 5.30 | 500 |
| Backups, small data transfer, misc allowance | 15.00 | 1,416 |
| Estimated subtotal | 174.49 | 16,468 |
| With 20% safety buffer | 209.39 | 19,761 |
| With 20% buffer plus 18% GST planning load | 247.08 | 23,319 |

Planning range: `INR 20,000 - 25,000/month`.

## Scenario 2: Standard School Production

Use for a normal single school production launch where availability matters but traffic is moderate.

Assumed capacity:

- 2 IOMAD web tasks: each `1 vCPU`, `2 GB`.
- 1 IOMAD cron task: `0.5 vCPU`, `1 GB`.
- RDS PostgreSQL `db.t4g.small`, Multi-AZ.
- 100 GB RDS storage.
- 100 GB EFS with 70% Standard and 30% IA blended estimate.
- Redis `cache.t4g.small`, 2 nodes.
- 1 ALB, 2 LCUs.
- 100 GB NAT data processing.
- 20 GB CloudWatch log ingestion.

| Cost Area | Monthly USD | Approx INR |
| --- | ---: | ---: |
| Fargate web and cron | 90.10 | 8,504 |
| RDS PostgreSQL | 70.45 | 6,649 |
| EFS `iomaddata` | 21.75 | 2,053 |
| Redis | 46.72 | 4,409 |
| ALB | 28.11 | 2,653 |
| NAT Gateway | 37.35 | 3,525 |
| CloudWatch | 10.60 | 1,000 |
| Backups, data transfer, misc allowance | 25.00 | 2,360 |
| Estimated subtotal | 330.08 | 31,153 |
| With 20% safety buffer | 396.10 | 37,383 |
| With 20% buffer plus 18% GST planning load | 467.40 | 44,112 |

Planning range: `INR 38,000 - 45,000/month`.

## Scenario 3: Full HA Production

Use for a larger school, higher concurrency, heavier course usage, more uploads, and stronger production operations.

Assumed capacity:

- 2 IOMAD web tasks: each `2 vCPU`, `4 GB`.
- 1 IOMAD cron task: `0.5 vCPU`, `1 GB`.
- RDS PostgreSQL `db.t4g.medium`, Multi-AZ.
- 200 GB RDS storage.
- 250 GB EFS with 60% Standard and 40% IA blended estimate.
- Redis `cache.t4g.small`, 2 nodes.
- 1 ALB, 3 LCUs.
- 150 GB NAT data processing.
- 30 GB CloudWatch log ingestion.

| Cost Area | Monthly USD | Approx INR |
| --- | ---: | ---: |
| Fargate web and cron | 162.18 | 15,307 |
| RDS PostgreSQL | 140.17 | 13,229 |
| EFS `iomaddata` | 47.50 | 4,483 |
| Redis | 46.72 | 4,409 |
| ALB | 33.95 | 3,204 |
| NAT Gateway | 39.60 | 3,737 |
| CloudWatch | 15.90 | 1,501 |
| Backups, data transfer, misc allowance | 40.00 | 3,775 |
| Estimated subtotal | 526.02 | 49,645 |
| With 20% safety buffer | 631.22 | 59,574 |
| With 20% buffer plus 18% GST planning load | 744.84 | 70,298 |

Planning range: `INR 60,000 - 75,000/month`.

## Scenario 4: Dev + Stage + Prod Running 24x7

Use this when the full three-environment setup runs continuously.

| Cost Area | Monthly USD | Approx INR |
| --- | ---: | ---: |
| Fargate web and cron | 306.34 | 28,912 |
| RDS PostgreSQL | 228.05 | 21,523 |
| EFS `iomaddata` | 84.25 | 7,952 |
| Redis | 105.12 | 9,921 |
| ALB | 84.31 | 7,958 |
| NAT Gateway | 110.70 | 10,448 |
| CloudWatch | 31.80 | 3,001 |
| Backups, data transfer, misc allowance | 80.00 | 7,550 |
| Estimated subtotal | 1,030.58 | 97,266 |
| With 20% safety buffer | 1,236.70 | 116,719 |
| With 20% buffer plus 18% GST planning load | 1,459.31 | 137,728 |

Planning range: `INR 1.15 lakh - 1.40 lakh/month`.

## Cost Control Recommendations

1. Keep `dev` and `stage` stopped or scaled down when not used.
2. Use `dev` without Multi-AZ RDS and with small ECS task sizes.
3. Keep production Multi-AZ enabled.
4. Monitor EFS growth every week. Uploaded videos can quickly change cost.
5. Use S3 or an external video platform for large recorded lectures instead of storing every video in IOMAD.
6. Avoid unnecessary NAT traffic by baking dependencies into images.
7. Set CloudWatch log retention based on environment:
   - Dev: 14 days.
   - Stage: 30 days.
   - Prod: 90 days or as required by policy.
8. Add AWS Budgets alerts at 50%, 80%, and 100% of expected monthly spend.

## Quoting Rule

Do not sell the product at raw AWS cost.

Minimum selling price should cover:

- AWS cost with buffer.
- GST/tax handling.
- Monitoring and incident support.
- Backup and restore operations.
- IOMAD upgrade work.
- School onboarding and training.
- Content/course migration.
- Product margin.

For Indian school SaaS pricing, use [Indian school product pricing model](indian-school-product-pricing.md).
