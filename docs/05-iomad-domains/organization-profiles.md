# Organization Profiles

`local_orgprofile` provides company-aware, organization-type-aware, and user-type-aware dynamic profiles without modifying Moodle or
IOMAD core. The tracked plugin source is `iomad-overrides/public/local/orgprofile`.

## Administration experience

The **Organization Profiles** landing page is the operational dashboard. It shows record counts and readiness for each configuration
layer, the recommended dependency order, aggregate runtime counts, and quick links. Each subpage has breadcrumbs, an explanation of
its purpose, and search/sort/page-size/pagination controls.

Use **Clear filters** to return to the default name ordering and 20-row page size. Sorting is restricted to server-defined columns,
and search text is passed through Moodle DML placeholders. Actions remain protected by the capability required for that page; the
improved navigation does not broaden authorization.

Run the authenticated, read-only administration-page smoke test after deployment:

```bash
make orgprofile-ui-smoke
```

The command uses local administrator credentials from `.env` without printing them. It verifies the dashboard and every listing for
HTTP success, expected page content, unresolved language placeholders, and Moodle exception output.

## Maintained configuration

The canonical configuration is:

`iomad-overrides/public/local/orgprofile/data/organization_profiles_master.csv`

It contains:

- the ten supported organization types;
- their business user types and profile forms;
- reusable core and custom field definitions;
- category and form-field placement mappings;
- required, read-only, visible, sensitive, uniqueness, options JSON, and validation JSON rules;
- ownership rules identifying concepts that must remain in Moodle or IOMAD.

The configuration includes School, University, Training Institute, Consulting Firm, Corporate Organization, Hospital / Healthcare,
Government Organization, NGO / Foundation, College, and Vocational Institute.

## Deploy the importer

Overrides are baked into the application image. After changing the importer or master CSV, run:

```bash
make sync-overrides
docker compose build iomad cron
docker compose up -d --wait --force-recreate iomad cron
docker compose exec -T iomad php admin/cli/upgrade.php --non-interactive
docker compose exec -T iomad php admin/cli/purge_caches.php
```

Rebuild and recreate both `iomad` and `cron`: they share Moodle data/cache storage and must run the same plugin code before caches
are purged.

## Validate without writing

Dry-run is the default and performs no database writes:

```bash
./scripts/import-orgprofile-configuration.sh
```

The equivalent Make target is:

```bash
make orgprofile-import
```

To validate another host-side CSV with the same columns:

```bash
./scripts/import-orgprofile-configuration.sh --file /absolute/path/configuration.csv
```

## Apply configuration

After reviewing the dry-run counts, apply atomically:

```bash
./scripts/import-orgprofile-configuration.sh --apply
```

Or:

```bash
make orgprofile-import ORGPROFILE_ARGS=--apply
```

The shell wrapper streams the host CSV to the Moodle CLI process. The importer uses plugin services and Moodle DML inside one
delegated transaction. It creates or updates records by stable shortname and is safe to run repeatedly. It never deletes records
that are absent from the CSV.

The import stores:

1. Organization Types.
2. User Types.
3. Profile Forms.
4. Field Library records.
5. Form Categories.
6. Form Field placements and overrides.

It deliberately does not store:

- IOMAD companies or company memberships;
- Company Mapping records;
- User Type Assignments;
- user profile values.

Those records depend on real tenant and user IDs and must not be inferred from CSV examples.

## Complete tenant setup after import

1. Open the IOMAD dashboard and choose **Create company with organization profile**.
2. Select one required organization type and enter the basic IOMAD company details. Creation uses
   `local_iomad\company::create_company()` and writes the mapping in the same delegated database transaction.
3. The organization type is now locked. **Company Mapping** can still change the optional default form or non-personal JSON
   configuration, but cannot change the organization type.
4. Use the standard IOMAD **Edit company** page for advanced branding, certificate, domain, email-template, role-template, parent,
   or dashboard settings.
5. Select the company in IOMAD, then choose **Create profiled user** from User administration.
6. Select a business user type. The plugin verifies that it belongs to the company's locked organization type and resolves the
   enabled user-type form (or an allowed generic/default form).
7. Complete the Moodle account and generated category/field form. Required, option, type, min/max, length, phone, regex, and
   company/site uniqueness rules are checked server-side before any account is created.
8. Submit. The plugin creates the Moodle account through `local_iomad\company_user::create()`, preserves the exact IOMAD company
   membership, writes the business user-type assignment, and saves values under `userid + companyid + fieldid`.
9. Open the resulting profile page to review the saved company-scoped profile.

For an existing company, map it once in **Organization Profiles → Company Mapping**, then start at step 5. Existing users can still
be handled through **User Type Assignment** followed by their organization profile page.

### Why there are dedicated IOMAD dashboard entries

The pinned IOMAD 5.1 company and company-user forms do not dispatch an extension hook that lets a local plugin inject and validate
additional form controls. The dedicated workflows are the supported no-core-change integration: they call the pinned IOMAD APIs and
appear beside the original IOMAD actions. The original actions remain available for legacy or advanced workflows, but they do not
enforce organization-profile completion.

## Safety and ownership rules

- Custom values are scoped by `userid + companyid + fieldid`.
- Sensitive fields require `local/orgprofile:viewsensitive` and `local/orgprofile:editsensitive`.
- Company/site uniqueness is enforced by the plugin value service and database uniqueness key.
- Username and profile photo remain Moodle-owned.
- Company and Department remain IOMAD-owned.
- Cohort, Course, Group, Role, and capabilities remain Moodle-owned.
- Business User Type never grants authorization.
- The V1 engine does not implement conditional expressions; the CSV uses explicit form placement overrides and explanatory notes.

## Direct Moodle CLI command

Inside the application container, the importer can also be run directly:

```bash
php public/local/orgprofile/cli/import_configuration.php
php public/local/orgprofile/cli/import_configuration.php --apply
```

Use `--file=/path/file.csv` for a container-side path or `--file=-` for standard input.
