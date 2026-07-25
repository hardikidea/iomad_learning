# Tenant Onboarding

1. Choose whether the institution is a parent company, child company, or standalone company.
2. Create or edit the CSV/YAML pack under `institution-packs/`.
3. Validate files: `make test` or `./scripts/validate-pack-files.sh <pack>`.
4. Generate operator workbooks: `make pack-workbooks PACK=<pack>`.
5. Review workbook values; export normalized CSV back to the pack.
6. Start local IOMAD and run `make pack-plan PACK=<pack>`.
7. Run `./scripts/institutionpack-cli.sh dry-run <pack>`.
8. Apply with `make pack-apply PACK=<pack>`.
9. Smoke-test the default URL and every tenant hostname.
10. Archive the JSON import report from `iomaddata/local_institutionpack/reports/`.

Demo packs use `example.local` addresses and demo-only passwords. Real packs must not commit personal data.
