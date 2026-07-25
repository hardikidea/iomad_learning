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
