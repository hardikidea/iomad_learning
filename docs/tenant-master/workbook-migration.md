# Workbook Migration

The reference ODS is a read-only functional specification:

`school_master_pack_2026_2027_full_predefined_master_macros.ods`

Verified SHA-256:
`02095b8fe31d765f62a0b9d659999c32264b01e0643fdabdce764aed50ad054e`

It was inspected without modifying the file and without executing LibreOffice
macros. The inventory found:

- 78 sheets: 8 control sheets and 70 data sheets;
- 831 header columns;
- 42 manual-input and 28 generated data domains;
- 239 list validations and 26 named ranges;
- a 2,478-line macro module;
- 140 formulas concentrated in dashboard/status behavior;
- a configured student capacity of 5,220;
- five failed next-year/alumni checks in the source workbook;
- a dashboard row limit of 2,000 that can undercount larger data sets.

Those source defects are not reproduced in the plugin. Database-backed
pagination, validation, and counts have no workbook row ceiling.

## Workbook To Domain

| Workbook area | Plugin/native destination |
|---|---|
| Trust and institution | Existing native IOMAD company + one Tenant Master academic profile link |
| Campuses/faculties/departments | IOMAD department hierarchy |
| Years/boards/mediums/grades/programmes/semesters/streams | Tenant Master semantics + Moodle categories |
| Subjects and templates | Tenant Master definitions + Moodle courses |
| Users | Native Moodle users and IOMAD membership |
| Principals, teachers, students, guardians, HOD/dean | Native scoped role assignments |
| Cohorts, memberships, groups, enrolments | Native Moodle/IOMAD records |
| Assessment/attendance | Native gradebook and completion configuration |
| Certificates | Native IOMAD certificate activities |
| Grades/completion/history | Read from native Moodle records |
| Promotion/progression | Versioned policy + previewed rollover plan |
| Next year/archive/alumni | Non-destructive lifecycle state; no history deletion |
| Dashboard/status/compatibility | Live validation, synchronization, drift, and audit screens |

## Macro To Service

| Workbook macro intent | Tenant Master behavior |
|---|---|
| `RefreshStatus` | Tenant validation and synchronization status |
| `GenerateAllDerivedSheets` | Automatic dependency projection or explicit Sync All |
| Individual `Generate*` | Module-specific dirty work and native adapter |
| `ClearAutomaticData` | No automatic destructive equivalent; guarded reconciliation only |
| `ResetAutomaticData` | Previewed, idempotent tenant reconciliation |
| Next-year generation | Rollover plan followed by elevated, backup-evidenced apply |

## Migration Rules

1. Treat ODS content as reference only.
2. Normalize approved data into a versioned ZIP containing CSV and
   `manifest.json`.
3. Remove passwords, tokens, contact details, and real personal data.
4. Create and configure the company in native IOMAD.
5. Initialise academic management in **Tenant Master > Managed institutions**.
6. Upload through **Imports** and review row actions/errors.
7. Apply only a valid plan.
8. Watch automatic synchronization, run validation, and retain the report.

Workbooks and macros are not executed in production or CI. CSV plus manifest
checksums are canonical for migration.
