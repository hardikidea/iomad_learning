# IOMAD Company Organization Structure Setup

This workflow provisions two separate structures for an existing IOMAD
company:

- native Moodle course categories for the complete learning-content taxonomy;
- high-level IOMAD departments for user-management and manager scope.

The canonical source is
[`moodle_iomad_category_grab_format.csv`](../institution-packs/categories/moodle_iomad_category_grab_format.csv),
using the exact five-column structure and content from the reviewed attachment.

It contains 598 category rows across 28 top-parent groupings. The CLI never
copies classes, streams, or subjects into departments. It creates at most 27
managed departments because the CSV `Organization` root maps to IOMAD's
existing company department root.

```text
IOMAD company course-category root
└── Organization                         <COMPANY>-CAT-ORG
    ├── Education and Academia           <COMPANY>-CAT-EDU
    │   ├── School                       <COMPANY>-CAT-SCH
    │   └── University                   <COMPANY>-CAT-UNI
    ├── IT                               <COMPANY>-CAT-ITD
    ├── Healthcare and Life Sciences     <COMPANY>-CAT-HLT
    └── ...remaining reviewed branches
```

The separate department tree for `ORGANIZATION=ALL` is:

```text
IOMAD company department root
├── Education and Academia (Department)
│   ├── School (Department)
│   └── University (Department)
├── Business, Management and Entrepreneurship (Department)
├── IT (Department)
├── Healthcare and Life Sciences (Department)
└── ...remaining high-level TOP PARENT organizations (Departments)
```

## CSV Contract

The command requires these headers in this exact order:

| Column | Meaning |
|---|---|
| `TOP PARENT` | Grouping and ancestry check. It must name the current category or one of its ancestors. |
| `PARENT-CATEGORY` | Parent display name. It is blank only for the single CSV root. |
| `CATEGORY-NAME` | Moodle category display name. |
| `CATEGORY-ID-NUMBER (SHORT-CODE)` | Unique stable code in the CSV, such as `CAT-SCH` or `CAT-CMW-AI`. |
| `DESCRIPTION` | Reviewed HTML category description. Unsafe HTML is cleaned with Moodle's HTML cleaner before storage. |

The supplied format uses repeated names such as `Science` and `English` in
different branches. The importer therefore does not resolve a parent by name
alone. It first matches `PARENT-CATEGORY`, then uses short-code ancestry when
more than one previous category has that name. It rejects missing, forward, or
ambiguous parent references and verifies every `TOP PARENT` value.

The CSV short codes are unique within the file but Moodle category idnumbers
are site-wide. At runtime the command prefixes every code with the selected
company shortname:

| CSV short code | Company | Moodle idnumber |
|---|---|---|
| `CAT-ORG` | `EXAMPLE_SCHOOL` | `EXAMPLE_SCHOOL-CAT-ORG` |
| `CAT-SCH` | `EXAMPLE_SCHOOL` | `EXAMPLE_SCHOOL-CAT-SCH` |
| `CAT-GRD-011-SCI-PH` | `EXAMPLE_SCHOOL` | `EXAMPLE_SCHOOL-CAT-GRD-011-SCI-PH` |

This prevents the same catalogue loaded for two companies from colliding.

## Department Contract

Departments are generated only from the unique `TOP PARENT` anchor rows. The
CSV root `Organization` represents the existing IOMAD company department root,
so it is not duplicated. Every other managed department receives:

- a visible name ending in `(Department)` so administrators cannot confuse it
  with a Moodle course category;
- a stable shortname generated as `ORGDEP_<CSV-SHORT-CODE>`, with hyphens
  converted to underscores;
- the nearest ancestor organization anchor as its IOMAD department parent.

For example:

| CSV anchor | IOMAD department name | Stable shortname | Parent |
|---|---|---|---|
| `CAT-EDU` | `Education and Academia (Department)` | `ORGDEP_CAT_EDU` | Company department root |
| `CAT-SCH` | `School (Department)` | `ORGDEP_CAT_SCH` | Education and Academia department |
| `CAT-UNI` | `University (Department)` | `ORGDEP_CAT_UNI` | Education and Academia department |

The importer rejects duplicate or over-length generated shortnames. It never
copies grade levels, classes, streams, tracks, subjects, courses, cohorts, or
groups into the IOMAD department tree. Course-category names remain exactly as
reviewed in the CSV; only department names receive the `(Department)` suffix.

### Complete `ALL` Department Map

| Department name | Stable shortname | Parent |
|---|---|---|
| Education and Academia (Department) | `ORGDEP_CAT_EDU` | Company department root |
| School (Department) | `ORGDEP_CAT_SCH` | Education and Academia (Department) |
| University (Department) | `ORGDEP_CAT_UNI` | Education and Academia (Department) |
| Business, Management and Entrepreneurship (Department) | `ORGDEP_CAT_BUS` | Company department root |
| IT (Department) | `ORGDEP_CAT_ITD` | Company department root |
| Banking, Financial Services and Insurance (Department) | `ORGDEP_CAT_BFS` | Company department root |
| Healthcare and Life Sciences (Department) | `ORGDEP_CAT_HLT` | Company department root |
| Engineering and Manufacturing (Department) | `ORGDEP_CAT_EN` | Company department root |
| Agriculture, Forestry and Fisheries (Department) | `ORGDEP_CAT_AGR` | Company department root |
| Energy, Utilities and Environment (Department) | `ORGDEP_CAT_ENE` | Company department root |
| Construction, Real Estate and Infrastructure (Department) | `ORGDEP_CAT_CON` | Company department root |
| Transportation, Logistics and Automotive (Department) | `ORGDEP_CAT_TRL` | Company department root |
| Hospitality, Travel and Tourism (Department) | `ORGDEP_CAT_HSP` | Company department root |
| Retail, E-Commerce and Consumer Services (Department) | `ORGDEP_CAT_RET` | Company department root |
| Media, Arts, Design and Communication (Department) | `ORGDEP_CAT_MDA` | Company department root |
| Government and Public Administration (Department) | `ORGDEP_CAT_GOV` | Company department root |
| Law, Justice and Compliance (Department) | `ORGDEP_CAT_LAW` | Company department root |
| Defence, Security and Emergency Services (Department) | `ORGDEP_CAT_DSE` | Company department root |
| Nonprofit, Social Care and International Development (Department) | `ORGDEP_CAT_NPO` | Company department root |
| Sports, Fitness and Wellness (Department) | `ORGDEP_CAT_SPW` | Company department root |
| Science, Research and Innovation (Department) | `ORGDEP_CAT_SRI` | Company department root |
| Professional and Advisory Services (Department) | `ORGDEP_CAT_PRS` | Company department root |
| Languages, Communication and English (Department) | `ORGDEP_CAT_LCE` | Company department root |
| Personal Development and Employability (Department) | `ORGDEP_CAT_PDE` | Company department root |
| Religion, Philosophy, Ethics and Culture (Department) | `ORGDEP_CAT_RPE` | Company department root |
| Skilled Trades and Vocational Education (Department) | `ORGDEP_CAT_TVE` | Company department root |
| Corporate Mandatory and Workplace Training (Department) | `ORGDEP_CAT_CMW` | Company department root |

## Prerequisites

1. Start the local Docker stack.
2. Create the IOMAD company through the normal IOMAD UI or supported company
   API so IOMAD creates its course-category root.
3. Note the exact company shortname. Do not use a company database ID.
4. Prefer a clean company category root. Existing categories with unrelated
   idnumbers are not adopted by display name.

Names in this guide such as `EXAMPLE_SCHOOL` are illustrative and are not
automatically created. Set a shell variable to the real shortname shown in the
IOMAD company administration page:

```bash
export IOMAD_COMPANY_SHORTNAME="replace_with_the_real_shortname"
```

## Plan

The CLI has two required parameters and one optional apply switch:

| Parameter | Required | Purpose |
|---|---:|---|
| `COMPANY` | Yes | Exact existing IOMAD company shortname |
| `ORGANIZATION` | Yes | Exact `TOP PARENT` name, or `ALL` |
| `APPLY=1` | No | Performs the reviewed changes; without it the command only plans |

Planning one organization is the default safe workflow and performs no
database writes:

```bash
make category-setup \
  COMPANY="$IOMAD_COMPANY_SHORTNAME" \
  ORGANIZATION="School"
```

This selects all rows whose `TOP PARENT` is `School` and automatically includes
the `Organization` and `Education and Academia` ancestors required by that
branch. The current CSV resolves this to 203 course categories and two managed
departments: `Education and Academia (Department)` and `School (Department)`.

To plan every organization in the CSV:

```bash
make category-setup \
  COMPANY="$IOMAD_COMPANY_SHORTNAME" \
  ORGANIZATION=ALL
```

`ORGANIZATION` matching is case-insensitive, but using the exact CSV name is
recommended. Names containing spaces or commas must be quoted. The JSON
response reports the selected organization, company root, total CSV rows,
selected category and department counts, idnumber prefix, and separate
`category_counts` and `department_counts`. `department_plan` lists every
selected department's display name, stable shortname, parent shortname, and
planned action. A conflict means a managed category or department exists
outside its expected company/parent relationship.

## Apply

After reviewing a conflict-free plan, repeat it with `APPLY=1`:

```bash
make category-setup \
  COMPANY="$IOMAD_COMPANY_SHORTNAME" \
  ORGANIZATION="School" \
  APPLY=1
```

Available organization selectors are the 28 unique `TOP PARENT` values in the
CSV:

```text
Organization
Education and Academia
School
University
Business, Management and Entrepreneurship
IT
Banking, Financial Services and Insurance
Healthcare and Life Sciences
Engineering and Manufacturing
Agriculture, Forestry and Fisheries
Energy, Utilities and Environment
Construction, Real Estate and Infrastructure
Transportation, Logistics and Automotive
Hospitality, Travel and Tourism
Retail, E-Commerce and Consumer Services
Media, Arts, Design and Communication
Government and Public Administration
Law, Justice and Compliance
Defence, Security and Emergency Services
Nonprofit, Social Care and International Development
Sports, Fitness and Wellness
Science, Research and Innovation
Professional and Advisory Services
Languages, Communication and English
Personal Development and Employability
Religion, Philosophy, Ethics and Culture
Skilled Trades and Vocational Education
Corporate Mandatory and Workplace Training
```

Apply every organization only after reviewing the corresponding `ALL` plan:

```bash
make category-setup \
  COMPANY="$IOMAD_COMPANY_SHORTNAME" \
  ORGANIZATION=ALL \
  APPLY=1
```

Apply is additive and idempotent. In CSV order it creates missing categories
and high-level departments. It updates managed category names, descriptions,
description format and visibility, and managed department names. It uses
`core_course_category::create()`, `core_course_category::update()`, and IOMAD's
`\local_iomad\company::create_department()` API inside a delegated transaction.

## Re-run and Missing-Record Recovery

Every managed category is matched by its company-prefixed Moodle `idnumber`,
not by its display name. Therefore running the same command again does not
create another copy.

After a complete successful `ORGANIZATION=ALL` apply, the next `ALL` plan
should report:

```json
{
  "category_counts": {
    "create": 0,
    "update": 0,
    "unchanged": 598,
    "conflict": 0
  },
  "department_counts": {
    "create": 0,
    "update": 0,
    "unchanged": 27,
    "conflict": 0
  }
}
```

The rerun rules are:

| Existing state | Plan result | `APPLY=1` behavior |
|---|---|---|
| Managed record exists and matches | `unchanged` | Validated; no write |
| Managed category or department is missing | `create` | Recreates only the missing record |
| Managed fields changed | `update` | Updates the same record through its owning API |
| Record has the wrong company or parent | `conflict` | Stops; does not duplicate or silently move it |

After an apply, the CLI validates every selected category and high-level
department inside the same transaction before committing. The JSON response
includes `post_apply_validation.validated_categories` and
`validated_departments`. If any managed record is missing or inconsistent, the
transaction rolls back.

Missing-category recovery applies only to these 598 CSV-managed categories
below the company category root. Missing-department recovery applies only to
the 27 generated high-level departments below the IOMAD company department
root. Both IOMAD-owned roots are prerequisites and are never recreated or
relinked by this command.

## Safety Boundary

- The command resolves the company from IOMAD's
  `local_iomad_companies.shortname` and validates its `coursecategoryid`.
- It never creates a company or repairs a missing company category root.
- It never creates courses or any other Moodle/IOMAD object.
- It never deletes categories or departments.
- It never moves a record when its existing parent conflicts with the CSV.
- It never assigns users, managers, roles, capabilities, courses, or licences
  to a department.
- It never adopts a legacy record merely because its display name matches.
- Category names and descriptions must not contain tenant secrets or personal
  data.

Course categories organize learning content; they are not an authorization
boundary. IOMAD company-course allocation, company membership, enrolment, and
Moodle roles/capabilities still control who can use courses. A site
administrator can see all company category trees.

## Permissions and Access Separation

The command creates structure only. It deliberately grants no role,
capability, manager assignment, company membership, course allocation,
licence, or enrolment.

| Requirement | Correct Moodle/IOMAD control | What this command contributes |
|---|---|---|
| Limit a tenant administrator to one company | IOMAD company-context role/capabilities | Company-owned roots only |
| Limit a manager to an organizational branch | IOMAD department manager assignment | Stable high-level department branch |
| Let staff maintain courses in a subject area | Moodle role assigned in the appropriate course-category context | Stable course-category branch |
| Let a teacher teach one course | Moodle course-context role | Nothing; course roles remain separate |
| Let a learner access training | Company membership, course allocation/licence, and enrolment | Nothing; categories do not enrol users |

In this IOMAD checkout, company administration capabilities use
`CONTEXT_COMPANY`. The relevant definitions are in
`blocks/iomad_company_admin/db/access.php`, including:

- `block/iomad_company_admin:companymanagement_view`;
- `block/iomad_company_admin:usermanagement_view`;
- `block/iomad_company_admin:coursemanagement_view`;
- `block/iomad_company_admin:assign_department_manager`;
- `block/iomad_company_admin:edit_all_departments`;
- `block/iomad_company_admin:edit_departments`.

Use the IOMAD UI to place a user in the required department and assign the
IOMAD department-manager type. Use Moodle's role-assignment UI separately when
the person also needs course-category or course permissions. Do not grant site
administrator to a tenant administrator, and do not infer Moodle roles from a
department name. The CLI intentionally does not create an automatic
department-to-course-category permission mapping.

## Company Lookup Troubleshooting

`COMPANY` must be the exact IOMAD company shortname, not the company display
name and not a value copied from an example. The command now reports these
prerequisite failures separately:

- `No IOMAD company exists with shortname ...`: create the company first or
  use its actual shortname.
- `references a missing course-category root`: the company row exists but its
  IOMAD-owned Moodle category was deleted. Do not repair this with a direct
  database update. Create a clean company through IOMAD or use the repository's
  supported local reset/reseed workflow.

Creating a top-level Moodle category whose idnumber matches the company
shortname does not repair the relationship. IOMAD continues using the
`coursecategoryid` stored on the company record. The CLI reports a matching but
unlinked category as a diagnostic hint and deliberately does not relink it.

For a new local company, sign in as site administrator and open:

```text
IOMAD Dashboard → Companies → Create company
```

The corresponding IOMAD 5.1 page is
`/blocks/iomad_company_admin/company_edit_form.php?createnew=1`. After saving,
run the plan with the new company's exact shortname before using `APPLY=1`.
