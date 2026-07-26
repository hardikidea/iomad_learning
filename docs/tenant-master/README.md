# Tenant Master

`local_tenantmaster` replaces the school master workbook and macro workflow with
a tenant-scoped IOMAD administration application. Normal operators do not need
a Tenant Master CLI or an institution-pack CLI.

Open **Site administration > Tenant Master**, select an IOMAD company, and use:

1. **Dashboard** to review native counts, adopt defaults, validate, or enqueue
   Sync All.
2. **Institution profile** for the combined trust/institution identity,
   hostname, contact information, and supported IOMAD branding fields.
3. **Organisation** for native IOMAD departments.
4. **Academic structure** for academic years, boards, mediums, grades,
   programmes, semesters, streams, subjects, templates, and policies.
5. **Courses** to inspect verified native category and course mappings.
6. **Users and roles** to create native Moodle users, assign scoped IOMAD
   business roles, and link guardians to learners.
7. **Cohorts and enrolments** for native cohorts, groups, memberships, and
   course access.
8. **Assessments**, **Certificates**, and **Progression** for policy status,
   native projections, and guarded academic rollover.
9. **Imports** for ZIP upload, inspection, plan, approval, apply, resume, and
   report.
10. **Synchronization**, **Validation**, and **Audit** for operations and
    evidence.

School operators should follow
[School management in Tenant Master](school-management.md) for the complete
menu sequence, native mapping, class placement, course-copy and annual
progression workflow.

All forms use the default IOMAD/Moodle renderer, navigation, tables,
breadcrumbs, sesskeys, capabilities, and responsive theme behavior. The plugin
does not install a theme.

## Automatic Processing

A validated create or edit commits its source record, calculates affected
dependencies, marks work dirty, and queues a Moodle ad-hoc task. A scheduled
recovery worker runs every minute. The web request does not perform a large
rebuild.

```mermaid
flowchart LR
    A["Tenant-scoped UI form"] --> B["Validate capability, sesskey, ownership and stable keys"]
    B --> C["Commit source"]
    C --> D["Calculate dependent native projections"]
    D --> E["Debounced dirty queue"]
    E --> F["Ad-hoc or scheduled worker"]
    F --> G["Supported Moodle or IOMAD API"]
    G --> H["Read back native record"]
    H --> I["Mapping, managed-field hash and audit"]
```

`Sync All` is a recovery/reconciliation action, not a required step after each
edit. Automatic synchronization is enabled by default. When paused in plugin
settings, dirty work remains visible and is not discarded.

## Native Data Rule

- Company, department, user, membership, role assignment, category, course,
  company-course assignment, cohort, group, enrolment, gradebook, completion,
  and certificate records are real IOMAD/Moodle records.
- The plugin stores only definitions without a complete native equivalent:
  academic master semantics, policy expressions, versioned defaults, import
  manifests, mapping hashes, queue state, validation, drift, rollover plans,
  and audit evidence.
- The plugin never duplicates a full native user profile and never stores a
  user password.
- Project code calls supported APIs. It does not write native tables directly.

## Safety

- A trust and its institution are one tenant business record linked one-to-one
  to an IOMAD company by stable `trust_code`.
- Child companies are used only for independently administered tenants.
  Campuses, faculties, and organisational units normally use departments.
- IOMAD departments and Moodle course categories are separate hierarchies.
- No workflow grants site administrator to a tenant role.
- There is no automatic deletion of companies, users, active courses,
  enrolments, grades, submissions, completion, certificates, or history.
- Destructive rollover reconciliation requires system-level capability,
  preview, confirmation, and a recovery-set reference.

## Documentation

- [Architecture and ownership](architecture.md)
- [Workbook migration](workbook-migration.md)
- [Data model](data-model.md)
- [Academic model](academic-model.md)
- [School management](school-management.md)
- [Roles and capabilities](roles-capabilities.md)
- [Synchronization and drift](synchronization.md)
- [Import packages](import-packages.md)
- [Operations and recovery](operations.md)
- [Ecosystem verification](ecosystem-verification.md)
- [Developer API map](developer.md)
- [Testing and acceptance](testing-acceptance.md)
- [CRUD and integration validation](crud-validation.md)

Platform installation, cron, upgrade, backup, and restore still use the normal
repository automation documented outside this section. That infrastructure
automation is not a prerequisite for everyday Tenant Master administration.
