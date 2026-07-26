# Tenant Master Developer Guide

## Pinned Compatibility

- IOMAD branch: `IOMAD_501_STABLE`
- Reviewed commit: `55b3128b8058d27f6cc4320850ca709ed5a792a9`
- Moodle/IOMAD: 5.1.5 build 20260608
- PHP: 8.2-8.4
- PostgreSQL: 15 or newer
- Plugin release: 1.2.0, schema version `2026072602`

## Native API Map

| Target | Supported call used |
|---|---|
| Company create/edit | Native IOMAD dashboard only; Tenant Master adopts by company code |
| Department create/update | `local_iomad\company::create_department()` |
| Root department | `local_iomad\company::get_company_parentnode()` |
| User create | `user_create_user()` |
| Company membership | `local_iomad\company::upsert_company_user()` |
| Course role/enrolment | `local_iomad\company_user::enrol()` and `role_assign()` |
| Guardian relationship | `role_assign()` at `context_user` |
| Category create/update | `core_course_category::create()` / `update()` |
| Course create/update | `create_course()` / `update_course()` |
| Course custom fields | `core_course\customfield\course_handler` and `core_customfield` controllers |
| Company course | `local_iomad\company::add_course()` |
| Cohort | `cohort_add_cohort()`, `cohort_update_cohort()`, `cohort_add_member()` |
| Group | `groups_create_group()`, `groups_update_group()`, `groups_add_member()` |
| Gradebook | `grade_category`, `grade_update()`, `grade_item` |
| Completion | `update_course()` |
| Certificate | `prepare_new_moduleinfo_data()`, `add_moduleinfo()`, module instance API |

Direct native table writes are forbidden. Native table reads are used for
same-tenant validation and exact post-write read-back.

## Service Boundaries

- Forms and `index.php` handle HTTP validation, capability, sesskey, redirect,
  and standard rendering.
- Native IOMAD forms are the sole manual CRUD surface for company, department,
  user, role, cohort, group, enrolment, licence and branding records.
- Application services enforce stable keys and tenant ownership.
- `queue_service` calculates dependencies and debounces work.
- `projection_service` owns locks, jobs, retries, read-back, and audit.
- `projection_adapter` isolates IOMAD-version behavior.
- Repositories access only Tenant Master source/metadata tables.
- UI, import, tasks, and rollover call the same services.

## Adding A Projection

1. Define whether the source is native-authoritative or has no native
   equivalent.
2. Add stable identity and field-level ownership.
3. Add same-tenant validation and dependency ordering.
4. Add the native API operation to the adapter/service.
5. Read back the native record and save a mapping only on exact success.
6. Add drift restoration through a supported API.
7. Add unit/integration, idempotency, isolation, and failure-path tests.
8. Update import schema, UI, capabilities, privacy, and these docs.

## Test Commands

```bash
php -l iomad-overrides/public/local/tenantmaster/index.php
$HOME/.composer/vendor/bin/phpcs \
  --standard=phpcs.xml.dist \
  iomad-overrides/public/local/tenantmaster
./scripts/test-phpunit.sh \
  public/local/tenantmaster/tests/crud_integration_test.php \
  public/local/tenantmaster/tests/lifecycle_test.php \
  public/local/tenantmaster/tests/default_service_test.php \
  public/local/tenantmaster/tests/projection_test.php \
  public/local/tenantmaster/tests/isolation_test.php \
  public/local/tenantmaster/tests/native_user_test.php \
  public/local/tenantmaster/tests/import_service_test.php
```

Also run `make test`, clean-install, upgrade, tenant-isolation, backup/restore,
and browser smoke checks before release.
