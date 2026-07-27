# Import Packages

Tenant Master import is available entirely in the plugin UI. It accepts a ZIP
with `manifest.json` at the root and normalized UTF-8 CSV files.

## Operator Workflow

Open **Tenant Master > Imports** for the intended institution. The page shows
required and optional fields for every supported entity and provides:

- an institution-specific starter ZIP with the correct trust code;
- individual header-only CSV downloads;
- sanitized examples under `examples/`;
- `field-guide.csv` with formats, examples and dependency notes;
- a README covering checksums, row counts and package assembly.

Root CSV files in the starter package contain headers only. Consequently, the
downloaded ZIP is a valid no-op package before it is edited. Example rows are
not referenced by the manifest and cannot be imported accidentally.

After editing a root CSV, recalculate its SHA-256 value and data-row count in
`manifest.json`. Remove unused file entries or retain their header-only CSV
with `rows` set to `0`. Upload the ZIP, select the least destructive import
mode, inspect the plan, and only then approve and apply it.

## Pipeline

```mermaid
flowchart LR
    A["Upload ZIP"] --> B["Inspect paths, schema and tenant"]
    B --> C["Verify SHA-256 and row counts"]
    C --> D["Validate headers, stable keys, duplicates and references"]
    D --> E["Persist immutable plan"]
    E --> F["Operator reviews actions/errors"]
    F --> G["Approve and apply"]
    G --> H["Per-row transaction and automatic native sync"]
    H --> I["Resume or final report"]
```

The original ZIP is not retained. Its checksum, manifest, normalized row
payloads, actions, results, and timestamps form the immutable batch evidence.

## Manifest

```json
{
  "schema_version": "1.0",
  "tenant": {
    "trust_code": "SCH_DEMO"
  },
  "files": [
    {
      "path": "academic_masters.csv",
      "entity": "academic_masters",
      "rows": 2,
      "sha256": "64-lowercase-hex-characters-calculated-from-the-csv"
    }
  ]
}
```

The actual `sha256` must be exactly 64 lowercase hexadecimal characters.
Absolute paths, `..`, backslashes, missing files, duplicate paths, and
unlisted entities are rejected.

## Supported CSV Entities

| Entity | Required columns | Native result |
|---|---|---|
| `academic_years` | `externalid,code,name,startdate,enddate,iscurrent` | Academic-year category |
| `academic_masters` | `mastertype,externalid,code,name` | Category/course/policy by type |
| `departments` | `externalid,shortname,name,parent_shortname` | IOMAD department |
| `cohorts` | `externalid,name` | Moodle cohort |
| `cohort_members` | `cohort_externalid,user_externalid` | Native cohort membership |
| `groups` | `externalid,name,course_idnumber` | Moodle course group |
| `group_members` | `group_externalid,course_idnumber,user_externalid` | Native group membership |
| `user_roles` | `user_externalid,rolekey,department_shortname,course_idnumber` | Scoped native role/access |
| `guardian_links` | `guardian_externalid,learner_externalid` | Native user-context mentor role |

Optional academic-master columns are `parent_externalid`, `description`,
`configurationjson`, `active`, and `sortorder`. Academic years optionally
accept `status`; cohorts optionally accept `description`.

Passwords, password-reset values, tokens, secrets, first/last name, email,
phone, and address columns are rejected. Create personal accounts through the
native-user UI or an approved identity/provisioning integration, then reference
their stable native idnumber in packages.

## Modes

- `create_only`: fail planning if the stable target already exists.
- `merge`: create missing rows and update supported existing rows.
- `update`: require an existing stable target.
- `deactivate_missing`: merge, then non-destructively deactivate missing
  academic masters after every row succeeds.

## Dependency And Resume Rules

Academic parents and department parents are sorted by depth. Years precede
masters; structural objects precede memberships and roles. Cross-tenant
references, missing native users/courses, duplicate keys, parent cycles,
checksum changes, and row-count changes block planning.

Each row applies in its own delegated transaction. Completed rows remain
completed. Reapplying a `completed_with_errors` batch processes only unfinished
rows and produces one final report.
