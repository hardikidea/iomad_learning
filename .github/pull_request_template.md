## Change

Describe the behavior and repository owner changed.

## Impact

- [ ] Tenant scope and role capabilities reviewed
- [ ] Personal data and secret handling reviewed
- [ ] Database schema or migration reviewed
- [ ] Backup, restore, rollback, or upgrade impact reviewed
- [ ] External provider and retry/idempotency behavior reviewed
- [ ] Service, endpoint, metric, alert, dashboard, and runbook docs updated

## Verification

List exact commands and results. Mark unavailable runtime gates as blocked.

- [ ] `make test`
- [ ] Moodle PHPCS
- [ ] Relevant PHPUnit suites
- [ ] Clean-install or previous-version upgrade test, when applicable
- [ ] Tenant-isolation and cross-company denial tests, when applicable
- [ ] Docker image and Compose validation
- [ ] Terraform validation, when applicable
- [ ] Backup/restore drill, when applicable

## Release

Record IOMAD commit, application image digest, migration decision, and rollback
recovery-set reference. Never propose a database downgrade.
