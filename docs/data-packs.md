# Data Packs

CSV/YAML is canonical. Workbooks are generated operator interfaces and are ignored by Git.

Required entity files:

- institutions, companies, domains, departments
- academic years, boards, mediums, programmes, grades, semesters, streams, subjects
- categories, course templates, courses
- users, roles, cohorts, groups, enrolments, parent links
- policies, licenses, branding

Import sequence:

1. `manifest.yml` defines schema version, pack ID, pack type, and CSV file map.
2. Validation checks required columns, duplicates, foreign keys, row counts, checksums, and password policy.
3. Plan compares stable IDs against the installed IOMAD database.
4. Dry run returns a plan without writes.
5. Apply uses Moodle/IOMAD APIs and writes an immutable JSON report.

Stable keys are company shortnames, department shortnames, category idnumbers, course shortnames, user idnumbers, cohort idnumbers, group idnumbers, policy keys, and license references.
