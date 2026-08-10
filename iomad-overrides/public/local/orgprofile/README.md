# Organization Profiles (`local_orgprofile`)

`local_orgprofile` adds dynamic, company-aware profile forms without changing Moodle or IOMAD core. The tracked source is
`iomad-overrides/public/local/orgprofile`; `make sync-overrides` deploys it to `iomad/public/local/orgprofile`.

## Administration experience

Open **Site administration > Plugins > Local plugins > Organization Profiles**. The dashboard reports configuration and runtime
counts, explains the Moodle/IOMAD/plugin ownership boundary, and presents the required setup order. Every catalogue page includes:

- a breadcrumb trail back to the plugin dashboard;
- a purpose and dependency note;
- server-side search, allow-listed column sorting, selectable page size, pagination, and an empty state;
- richer relationship/status columns and Moodle-native action controls.

Recommended setup order: organization types, user types, field library, profile forms, categories, form-field placements, company
mapping, then company-scoped user assignment. An existing company mapping keeps its organization type immutable.

After deployment, run `make orgprofile-ui-smoke`. It signs into the local site using the administrator values already held in
`.env`, requests every plugin administration list, and fails on HTTP errors, Moodle exceptions, or unresolved `[[language]]`
placeholders. It does not print credentials or profile values and does not change site data.

## Model and tenant boundary

The resolution chain is IOMAD company → organization type → user type → profile form → categories → reusable fields. Every
classification and custom value is keyed by both Moodle user ID and the exact IOMAD company ID. A parent-company relationship does
not make a profile value visible in another company.

The plugin relies on the pinned IOMAD 5.1 APIs and schema:

- `local_iomad\custom_context\context_company` and `local_iomad\iomad::has_capability()` for tenant authorization;
- `{local_iomad_companies}` for company identity;
- `{local_iomad_company_users}` for exact membership and manager assignments;
- `{local_iomad_company_departments}` remains IOMAD-owned and is not duplicated.

See `iomad/public/local/iomad/classes/custom_context/context_company.php`, `classes/iomad.php`, `classes/company.php`, and
`db/install.xml` in the pinned checkout.

## Setup

1. Run `make sync-overrides` and complete the Moodle upgrade.
2. In **Site administration → Plugins → Local plugins → Organization Profiles**, create organization types and user types.
3. Create reusable fields. JSON validation accepts only `required`, `minlength`, `maxlength`, `min`, `max`, `email`, `url`,
   `integer`, `date`, `phone`, and an optional trusted-administrator `regex`.
4. Create forms and categories, then attach ordered fields under **Form Fields**.
5. In the IOMAD dashboard, use **Create company with organization profile**. Organization type is required and becomes immutable
   when the IOMAD company and mapping are created. Existing companies can be mapped once under **Company Mapping**.
6. Select the company in IOMAD and use **Create profiled user**. Select a business user type; the matching profile form is resolved
   and displayed before the Moodle account is created.
7. Submit the form. Account data and every editable dynamic value are validated server-side, then the Moodle user, exact IOMAD
   membership, user-type assignment, and company-scoped profile values are stored in one delegated transaction.
8. Open `/local/orgprofile/profile.php?userid=USERID&companyid=COMPANYID` or use the link added to authorized user navigation.

The standard IOMAD **Create company** and **Create user** pages remain unchanged because IOMAD 5.1 does not expose a form-injection
hook for those forms. Use the plugin's two dashboard entries when an organization mapping and completed dynamic profile are mandatory.
After creation, use IOMAD **Edit company** for advanced branding, certificates, templates, domains, and parent-company settings.

## Maintained configuration import

The repository maintains the ten-organization configuration in
`data/organization_profiles_master.csv`. From the repository root, validate it without writes:

```bash
./scripts/import-orgprofile-configuration.sh
```

Apply it atomically after reviewing the counts:

```bash
./scripts/import-orgprofile-configuration.sh --apply
```

The import is idempotent, updates by stable shortname, and never deletes absent records. It stores organization types, user types,
forms, fields, categories, and placements. Actual IOMAD company mappings, company user-type assignments, and profile values remain
explicit tenant operations because their IDs must be verified against the live IOMAD company relationship.

See `docs/05-iomad-domains/organization-profiles.md` in the project repository for deployment, custom-file, direct CLI, and safety
instructions.

The own-edit setting is disabled by default. Sensitive fields require the dedicated view/edit capabilities and are never included in
generic management lists or event descriptions.

Referenced Moodle fields are site-global user properties, not tenant-scoped copies. They are updated through `user_update_user()` and
validated with Moodle's core field definitions and duplicate-email policy. When Moodle requires confirmation for a user's own email
change, this plugin keeps that field read-only so the change continues through Moodle's core profile workflow.

## Example configuration

School:

- User types: Student, Teacher, Parent, Principal.
- School Student Profile / Identity: Admission Number (company unique), GR Number, Date of Birth, Gender.
- School Student Profile / Address: Address Line 1, core City, District, State, PIN Code.
- School Student Profile / Guardian: Guardian Name, Guardian Mobile.

Corporate:

- User types: Employee, Instructor, Manager.
- Corporate Employee Profile / Employment: Employee ID (company unique), Job Title, Job Level, Work Location.
- Corporate Employee Profile / Emergency: Emergency Contact Name, Emergency Contact Mobile.

India-specific mobile and PIN examples should use administrator-configured regex rules such as `/^[0-9]{10}$/` and
`/^[0-9]{6}$/`; the generic engine does not hardcode a country policy.

## Privacy and limitations

The Privacy API exports and deletes rows in `{local_orgprofile_user}` and `{local_orgprofile_value}` in the user's context. The
organization, form, category, field, and company-mapping tables are configuration records and are not intended to contain personal
data. `configjson`, field defaults, labels, and descriptions must therefore remain non-personal configuration.

V1 intentionally excludes conditional expressions, dependent dropdowns, uploads, approvals, external APIs, encryption key
management, and drag-and-drop building. Values marked sensitive are access-controlled but not encrypted at rest.

## Compatibility note

Release 1.3.1 converts canonical date and datetime values to integer timestamps before supplying Moodle's date form controls.
This applies to both new profiled-user forms and existing profile edit forms, including optional fields with empty defaults.

Release 1.3.2 presents profiled-user form sections as an accessible accordion. The account section opens initially, opening
another section closes the current section, and Moodle's conflicting expand-all control is hidden on that page.

Release 1.3.3 gives core-mapped Country fields precedence over their library datatype, so create and edit forms obtain the
localized, site-enabled country list from Moodle instead of treating Country as an empty administrator-configured menu.

Release 1.3.4 loads Moodle's user API before delegating account creation to IOMAD. On validation failure, the profiled-user
accordion opens the first panel containing an error and focuses its invalid control so the problem is immediately visible.
