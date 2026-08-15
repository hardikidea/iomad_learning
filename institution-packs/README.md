# Institution Packs

CSV and YAML files are the canonical source for tenant onboarding. Workbooks are operator interfaces generated from these files and must not be treated as source of truth.

Pipeline:

1. Edit canonical master CSV/YAML files.
2. Generate review XLSX or macro-enabled ODS workbooks.
3. Export normalized CSV.
4. Validate manifest, row counts, duplicate keys, foreign keys, and checksums.
5. Run CLI plan.
6. Run dry run.
7. Apply through `local_institutionpack`.
8. Archive the immutable import report.

Do not commit real personal data. Demo rows must use `example.local` addresses and demo-only passwords.

## Sanitized Demo Packs

The School and University `master/` and `sample/` directories are generated
deterministically:

```bash
make demo-generate
make demo-check
```

Do not edit generated CSVs directly. Change `scripts/generate-demo-packs.py`,
regenerate, review the complete diff, and validate with `make test`.

The reviewed category hierarchy under `categories/` is a separate,
operator-owned source and is not generated with the demo packs. It uses the
five-column CSV supplied for category setup and creates only Moodle course
categories plus high-level IOMAD permission-scope departments below an existing
company. Classes, streams, and subjects remain categories. Use the plan-first
`make category-setup COMPANY=... ORGANIZATION=...` workflow documented in
`docs/category-setup.md`.

The local clear/reseed procedure and expected counts are documented in
`docs/demo-reset-and-reseed.md`.
