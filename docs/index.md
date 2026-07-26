# IOMAD Learning Documentation

This documentation describes the repository-owned IOMAD 5.1 platform. The
official source remains a detached, reproducible checkout at the commit in
`versions.env`; project behavior lives in `iomad-overrides/`.

## Start Here

- [Repository setup](setup.md)
- [Tenant Master UI administration](tenant-master/README.md)
- [Tenant Master architecture and native ownership](tenant-master/architecture.md)
- [Tenant Master native-first administration](tenant-master/native-first-administration.md)
- [Tenant Master workbook migration](tenant-master/workbook-migration.md)
- [Tenant Master roles and capabilities](tenant-master/roles-capabilities.md)
- [Tenant Master automatic synchronization](tenant-master/synchronization.md)
- [Tenant Master import packages](tenant-master/import-packages.md)
- [Tenant Master operations](tenant-master/operations.md)
- [Tenant Master testing and acceptance](tenant-master/testing-acceptance.md)
- [Demo reset and reseed](demo-reset-and-reseed.md)
- [Architecture boundaries](03-architecture/component-boundaries.md)
- [Tenant onboarding](tenant-onboarding.md)
- [Data packs](data-packs.md)
- [Backup and restore](backup-restore.md)
- [Upgrade](upgrade.md) and [rollback](rollback.md)
- [Security](security.md)
- [CI/CD](ci-cd.md)
- [Operator runbook](runbook.md)
- [Health and observability](11-operations/health-and-observability.md)
- [Service catalogue](11-operations/service-catalogue.md)
- [Exception catalogue](11-operations/exception-catalogue.md)
- [Global events and gamification](05-iomad-domains/gamification-and-global-events.md)
- [Product icon system](icon-system.md)

## Evidence And Governance

- [Capability register](feature-capability-matrix.md)
- [Compatibility matrix](compatibility-matrix.md)
- [Product acceptance policy](product-suite-acceptance.md)
- [Architecture decisions](adr/README.md)
- [Documentation audit](audits/existing-documentation-audit.md)
- [Documentation migration map](audits/documentation-migration-map.md)
- [Machine-readable inventory](audits/documentation-inventory.json)
- [Ownership and change control](14-governance/ownership-and-change-control.md)
- [Master-prompt compliance](audits/master-prompt-compliance.md)
- [Prompt conflict decisions](audits/prompt-conflict-report.md)
- [Documentation debt](audits/documentation-debt.md)
- [Documentation validation report](audits/documentation-validation-report.md)

Documentation claims are acceptance inputs, not substitutes for tests. A
feature is supported only when its implementation, tenant boundary, failure
behavior, tests, and operator procedure are all present.
