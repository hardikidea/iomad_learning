# Roles And Capabilities

## Business Role Mapping

| Business role | Native role | Scope | IOMAD manager type | Intended use |
|---|---|---|---:|---|
| Principal / Registrar | `companymanager` | Company | 1 | Tenant management |
| Trustee / Management | `companyreporter` | Company | 4 | Read/report oversight |
| IT coordinator | `institutionitcoordinator` | Company | 0 | Limited company user and profile administration |
| Teacher / Faculty | `editingteacher` | Course | 0 | Company educator and course teacher |
| Student / Learner | `student` | Course | 0 | Course participant |
| Parent / Guardian | `tenantguardian` | Learner user context | 0 | Explicit learner mentor relationship |
| HOD / Dean | `companydepartmentmanager` | Department | 2 | Department management/reporting |

Teacher and student assignments require a course assigned to the same company.
HOD/dean assignment requires a department in that company. Guardian assignment
requires both people to be members of the same company. Site administrator is
never granted by role mapping.

## Plugin Capabilities

| Capability | Default tenant roles | Purpose |
|---|---|---|
| `local/tenantmaster:view` | Manager, department manager, reporter, IT coordinator | Open tenant-scoped screens |
| `manageprofile` | Manager, IT coordinator | Institution type and non-native regulatory metadata |
| `manageorganisation` | Manager, department manager, IT coordinator | Legacy/import adapter; manual CRUD is native IOMAD |
| `manageacademic` | Manager, department manager | Academic years, masters and policies |
| `managepeople` | Manager, department manager, IT coordinator | Placement/import automation over native records |
| `manageroles` | Manager, IT coordinator | Role mapping used by approved automation |
| `sync` | Manager | Sync All and retry |
| `import` | Manager | Package inspection and apply |
| `viewaudit` | Manager, department manager, reporter | Validation and audit |
| `resolvedrift` | Manager | Explicit drift resolution |
| `destructive` | Moodle manager at system context | Guarded rollover/reconciliation |

Every mutating page requires login, tenant resolution, capability, sesskey, and
same-tenant references. Company reporters have read/audit access and cannot
alter academic or native records.

## IT Coordinator Capability

`institutionitcoordinator` is a dedicated company-context role. It is not an
IOMAD manager type and is never a site administrator. Where the pinned IOMAD
release exposes them, it receives only company view and company-user
create/update/upload capabilities, plus Tenant Master institution metadata and
placement workflows. Import, Sync All, drift resolution,
academic-policy management, destructive reconciliation, and site
administration remain denied.

## Guardian Capability

The plugin creates `tenantguardian` only if it does not already exist, permits
it at user context, allows `moodle/user:viewdetails`, and prevents broad
`moodle/user:viewalldetails`. A guardian sees a learner only through an
explicit native role assignment on that learner's user context. Upgrade logic
migrates legacy `parentguardian` assignments to this canonical role without
changing the learner relationship.

## Review Procedure

Before changing a native role:

1. Export or record the current role definition.
2. Review every capability and context level.
3. Update the native role through Moodle/IOMAD role administration.
4. Verify native company managers, department managers and course roles in
   IOMAD, then confirm all seven semantic mappings through validation.
5. Run Tenant Master validation and tenant-isolation tests.
6. Test one user of each affected business role without site-admin access.
