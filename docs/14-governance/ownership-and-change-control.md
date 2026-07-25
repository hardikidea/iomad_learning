# Ownership And Change Control

## Ownership

| Area | Required reviewer | Evidence |
|---|---|---|
| IOMAD pin and upgrade | platform engineering and LMS operations | source verification, clean install, upgrade test |
| tenant scope and capabilities | IOMAD security owner | cross-company denial and role tests |
| database schema | plugin owner and data owner | XMLDB validation, upgrade path, backup decision |
| Terraform and deployment | cloud platform owner | fmt, validate, plan review, recovery impact |
| backup or restore | operations and data owner | matching recovery set, restore drill |
| payments and identity | security and integration owner | provider fixtures, replay, privacy review |
| observability | operations owner | metric cardinality, alert/runbook, failure drill |
| institution schemas | data-pack owner | schema, checksum, idempotency validation |

`CODEOWNERS` uses repository-local placeholder ownership because no GitHub
organization/team names are committed in this workspace. Replace the
placeholder with actual teams before branch protection or production approval.

## Change Checklist

1. Classify tenant, privacy, schema, recovery, and external-integration impact.
2. Update the owning code, tests, canonical documentation, service/endpoint
   catalogue, and runbook in one pull request.
3. Preserve the pinned IOMAD checkout; project code belongs in
   `iomad-overrides/`.
4. Run static validation and the applicable clean-install, upgrade,
   tenant-isolation, browser, backup, or restore gates.
5. Record commands, immutable image and source SHAs, environment, date, and
   blocked checks. Never convert a blocked runtime gate into a pass.
6. Require environment approval for stage and production deployment.

## Documentation Definition Of Done

- one canonical owner for each subject;
- no stale source names, floating branches, `latest` deployment tags, or
  production `git pull`;
- commands and paths verified against source;
- diagrams stored as Mermaid text and reviewed with the implementation;
- status words mean `implemented`, `validated`, `blocked`, `planned`, or
  `rejected`; marketing claims are not acceptance evidence;
- external URLs and time-sensitive claims require owner revalidation;
- deleted or superseded instructions retain a migration record.

