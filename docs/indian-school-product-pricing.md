# Indian School Product Pricing Model

This document proposes product packages for selling IOMAD Learning IOMAD to Indian schools. Prices are business planning numbers, not legal or tax advice. Add GST as applicable and confirm final pricing with finance.

## Positioning

Sell this as a managed school learning platform, not as raw IOMAD hosting.

Value proposition:

- Branded school LMS.
- Admin, teacher, student, and parent-ready workflows.
- Course categories for CBSE, ICSE, and State Board structure.
- Assignment, quiz, H5P, content bank, file upload, and gradebook setup.
- Managed AWS hosting.
- Backups, monitoring, security checks, and upgrades.
- Teacher onboarding and support.

## Target Customer Segments

| Segment | Typical Size | Buying Trigger | Recommended Package |
| --- | ---: | --- | --- |
| Small private school | 300-800 students | First LMS or post-demo adoption | Starter or Standard |
| Mid-size CBSE/ICSE school | 800-2,000 students | Structured digital learning and exams | Standard |
| Large school or group | 2,000-5,000 students | Reliability, audit, training, integrations | Premium or Enterprise |
| Coaching / test prep | 500-10,000 learners | Quizzes, assignments, analytics | Premium or Enterprise |

## Pricing Principles

1. Keep a monthly minimum because AWS has fixed base costs.
2. Price by active students for scale, but protect margin with a minimum commitment.
3. Charge setup separately. Onboarding, branding, training, and migration are real work.
4. Charge annual plans upfront when possible. Indian schools often prefer annual academic-year budgeting.
5. Keep GST separate in commercial proposals.
6. Do not include large video hosting in the base plan unless priced separately.

## Suggested Packages

### Plan A: Pilot Launch

Use for a 60-90 day trial or one academic department.

| Item | Value |
| --- | --- |
| Target | Up to 300 active students |
| Infrastructure | Small single environment, not full HA |
| Included | Basic branding, 10 courses, 20 teachers, email support |
| Monthly selling price | `INR 35,000 - 45,000/month` plus GST |
| One-time setup | `INR 50,000 - 75,000` plus GST |
| Term | 2-3 months |

Use this only as a controlled pilot. For full school production, move to Standard or Premium.

### Plan B: Starter School

Use for a small school that needs managed IOMAD but has limited traffic.

| Item | Value |
| --- | --- |
| Target | Up to 700 active students |
| Infrastructure | Small production environment |
| Included | School branding, 30 courses, user upload support, monthly backup check, standard support |
| Monthly selling price | `INR 55,000 - 75,000/month` plus GST |
| One-time setup | `INR 75,000 - 1,25,000` plus GST |
| Annual prepaid option | 10% discount |

This plan should avoid heavy video storage. Use YouTube private/unlisted, Vimeo, S3, or another content platform for large videos.

### Plan C: Standard Dedicated School

Recommended default package for a serious school deployment.

| Item | Value |
| --- | --- |
| Target | 700-1,800 active students |
| Infrastructure | Standard production with Multi-AZ database |
| Included | Branded IOMAD, dev/stage/prod pipeline, monitoring, backups, 75 courses, teacher onboarding, monthly health report |
| Monthly selling price | `INR 1,10,000 - 1,45,000/month` plus GST |
| One-time setup | `INR 1,50,000 - 2,50,000` plus GST |
| Annual prepaid option | 10-12% discount |

Effective per-student pricing at 1,500 students:

```text
INR 73 - 97 per student per month
```

### Plan D: Premium HA School

Use for larger schools, higher concurrency, exams, and stronger SLA expectations.

| Item | Value |
| --- | --- |
| Target | 1,800-3,500 active students |
| Infrastructure | Full HA production with larger web tasks, Multi-AZ database, Redis HA, alarms, restore drills |
| Included | All Standard features, priority support, quarterly upgrade planning, 150 courses, admin training, teacher training, restore test in stage |
| Monthly selling price | `INR 1,75,000 - 2,50,000/month` plus GST |
| One-time setup | `INR 2,50,000 - 4,00,000` plus GST |
| Annual prepaid option | 12-15% discount |

Effective per-student pricing at 3,000 students:

```text
INR 58 - 83 per student per month
```

### Plan E: Enterprise / School Group

Use for multi-campus schools, school chains, or coaching institutions.

| Item | Value |
| --- | --- |
| Target | 3,500+ active students or multiple branches |
| Infrastructure | Custom sizing and optional separate environments per school |
| Included | Dedicated account architecture, advanced reports, integrations, custom roles, higher support SLA |
| Monthly selling price | `INR 3,00,000+/month` plus GST |
| One-time setup | `INR 5,00,000+` plus GST |
| Contract | Annual or multi-year |

## Optional Add-Ons

| Add-On | Suggested Price |
| --- | ---: |
| Additional storage block, 100 GB | `INR 5,000 - 12,000/month` |
| Heavy video hosting package | Quote separately |
| Extra production-like environment | `INR 35,000 - 75,000/month` |
| Custom theme/branding | `INR 50,000 - 1,50,000 one-time` |
| Course migration | `INR 1,500 - 7,500/course` based on complexity |
| Bulk user/course setup | `INR 15,000 - 50,000 one-time` |
| Teacher training session | `INR 15,000 - 35,000/session` |
| On-site training day | `INR 35,000 - 75,000/day` plus travel |
| Custom report | `INR 25,000 - 1,00,000/report` |
| SSO integration | `INR 75,000 - 2,50,000 one-time` |
| WhatsApp/SMS integration | Setup plus provider message cost |
| Annual restore drill | `INR 50,000 - 1,25,000/year` |

## Cost-To-Price Formula

Use this formula before sending a proposal:

```text
Monthly Selling Price =
  AWS Monthly Cost With Buffer
  + Support Hours
  + Customer Success / Training Allowance
  + Backup / Upgrade Operations Allowance
  + Gross Margin
```

Example for Standard Dedicated:

| Component | Example Amount |
| --- | ---: |
| AWS cost with buffer and tax planning | `INR 44,000` |
| Support, 12-18 hours/month | `INR 25,000 - 40,000` |
| Customer success and admin help | `INR 10,000 - 20,000` |
| Upgrade/backup operations reserve | `INR 10,000 - 20,000` |
| Business margin | `35% - 50%` |
| Suggested monthly selling price | `INR 1,10,000 - 1,45,000` plus GST |

## Academic-Year Pricing

Many Indian schools budget annually. Offer both monthly and annual pricing.

| Package | Monthly Price | Annual Price Guidance |
| --- | ---: | ---: |
| Pilot Launch | `INR 35,000 - 45,000` | Not recommended as annual |
| Starter School | `INR 55,000 - 75,000` | `INR 5.9L - 8.1L` after 10% annual discount |
| Standard Dedicated | `INR 1.10L - 1.45L` | `INR 11.9L - 15.7L` after 10% annual discount |
| Premium HA | `INR 1.75L - 2.50L` | `INR 18.4L - 26.4L` after 12% annual discount |
| Enterprise | `INR 3.00L+` | Custom |

All prices should be quoted plus GST unless the commercial proposal explicitly says GST is included.

## Per-Student Pricing Alternative

Use per-student pricing when the school wants a simple model.

| Package | Suggested Rate | Minimum Monthly Billing |
| --- | ---: | ---: |
| Starter | `INR 75 - 110/student/month` | `INR 55,000/month` |
| Standard | `INR 65 - 95/student/month` | `INR 1,10,000/month` |
| Premium | `INR 55 - 85/student/month` | `INR 1,75,000/month` |

Use active students, not total historical users. Define active student clearly in the contract.

Recommended definition:

```text
An active student is any student account that can log in during the billing month.
```

## SLA Recommendations

| Package | SLA Target | Support |
| --- | --- | --- |
| Pilot | Best effort | Email, 2 business day response |
| Starter | 99.0% monthly uptime | Email/WhatsApp, 1 business day response |
| Standard | 99.5% monthly uptime | Priority email/WhatsApp, 8 business hour response |
| Premium | 99.9% monthly uptime target | Priority support, 4 business hour response |
| Enterprise | Custom | Contract SLA |

Do not promise 99.9% unless production architecture, monitoring, backups, restore drills, and operational coverage match that promise.

## Sales Proposal Checklist

Before sending a quote, confirm:

- Number of active students.
- Number of teachers and admins.
- Expected concurrent users during exams.
- Number of courses.
- Expected file/video storage.
- Whether videos will be stored in IOMAD or external platform.
- Required domain name.
- Branding requirements.
- Email/SMS/WhatsApp requirements.
- Data retention and backup expectations.
- Training requirement.
- Migration requirement.
- Support hours and SLA expectation.
- Annual or monthly billing.
- GST handling.

## Recommended Starting Offer

For a typical private school with 1,000-1,500 students:

```text
Plan: Standard Dedicated School
Monthly: INR 1.25L + GST
Setup: INR 1.75L + GST
Annual prepaid: INR 13.5L + GST
Includes: AWS hosting, IOMAD setup, branding, monitoring, backups, 75 courses, teacher training, monthly health report
Excludes: large video hosting, SMS/WhatsApp provider fees, custom integrations, on-site travel
```

This gives enough room for AWS cost, support, training, product maintenance, and margin.
