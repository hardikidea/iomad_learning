# Plugin Compatibility Policy

Only project-owned plugins or reviewed third-party releases that explicitly support IOMAD/Moodle 5.1 belong in `iomad-overrides/`.

## Admission Gate

A plugin change must satisfy all of these checks:

1. `version.php` declares a valid `$plugin->supported` range containing `501`.
2. The plugin has no unresolved required-plugin dependency.
3. PHP syntax, Moodle coding standards, privacy API, scheduled-task, backup/restore, and uninstall checks pass.
4. Clean installation and previous-version upgrade tests pass.
5. Tenant-isolation and permission tests pass for any company-aware behavior.
6. The release, source, license, reviewed commit/tag, and owner are recorded in the pull request.
7. The plugin is exercised on PHP 8.2, 8.3, and 8.4 before production promotion.

Run the static admission check with:

```bash
./scripts/validate-plugin-compatibility.sh
```

## Active Supported Overrides

| Component | Ownership | Declared support | Purpose |
|---|---|---:|---|
| `block_dash` | Third party | 4.5-5.1 | Configurable dashboard blocks |
| `format_designer` | Third party | 4.4-5.1 | Course presentation format |
| `tool_courserating` | Third party | 4.5-5.2 | Course ratings |
| `local_institutionpack` | Project | 5.1 | Institution-pack import and audit |
| `theme_iomad_learning` | Project | 5.1 | White-label Boost child theme |
| `tool_iomadmonitor` | Project | 5.1 | Service registry, health and observability |
| `local_global_events` | Project | 5.1 | Tenant events, gamification and messaging |
| `local_iomad_h5p_bridge` | Project | 5.1 | Trusted H5P reward adapter |
| `local_iomad_scorm_gen` | Project | 5.1 | SCORM package generator and reward adapter |
| `block_gamification_telemetry` | Project | 5.1 | Current-learner progress feedback |

## Removed Unsupported Overrides

The following inherited Moodle-era plugins were removed because their package metadata did not explicitly support 5.1:

| Component | Removal consequence |
|---|---|
| `mod_hvp` | Legacy H5P activities are deleted during Moodle API uninstall; use core H5P/content bank after a reviewed migration |
| `mod_certificatebeautiful` | Beautiful Certificate activities and plugin data are deleted during API uninstall |
| `block_whatsapp_messenger` | Block instances and plugin configuration are deleted |
| Legacy unsupported course-format package | Courses must use a supported format before uninstall |

Never delete plugin files before running `admin/cli/uninstall_plugins.php`. On a shared environment, take and verify a matching recovery set first, stop cron, enable maintenance, perform the API uninstall, remove the tracked override, rebuild the immutable image, run the CLI upgrade, and complete tenant smoke tests.

## Commercial Reporting Candidates

`local_learnerscript` and `local_intelliboard` are procurement candidates, not
active supported overrides. Public marketing or generic Moodle compatibility
does not establish IOMAD 5.1 tenant isolation. Before either component can be
added, run `make reporting-validate` with a protected, checksum-pinned licensed
artifact and complete the acceptance work in
`docs/commercial-reporting-integrations.md`.

Do not commit vendor packages unless the license explicitly permits private
source control. Never download a commercial plugin from a floating URL during
an image build.

## Excluded Upstream Components

`iomad-overrides/.iomad-exclusions` is an allowlist of reviewed upstream paths
that must not enter a deployable image. `scripts/apply-iomad-overrides.sh`
validates each path against the pinned checkout and fails an upgrade when an
entry disappears, forcing explicit review.

Tracked upstream hotfixes are separately declared in
`iomad-overrides/.iomad-tracked-overrides`. Each entry records the SHA-256 of
the unmodified pinned-upstream file. Override application fails if that hash
changes, so upgrades cannot silently reuse a stale patch. Host sync skips these
files to keep the ignored upstream checkout clean; immutable image builds apply
them after checksum validation.

| Component | Reason | Supported replacement |
|---|---|---|
| `auth_iomadsaml2` | Disabled in the baseline and bundles obsolete SimpleSAMLphp, Composer, Symfony, and Twig versions with unresolved high/critical findings | `auth_iomadoidc`, core OAuth 2, or an independently reviewed SAML plugin |

Existing environments must disable the authentication method and migrate its
users before exclusion. The baseline demo database was removed through
Moodle's `uninstall_plugin()` API while maintenance mode was enabled; no plugin
tables or configuration were deleted directly.

The baseline includes nine checksum-guarded compatibility or integration
overrides:

- `local/iomad/version.php` no longer requires the excluded plugin.
- The company advanced-settings page loads SAML support only when its library
  exists; OIDC and other company settings remain available.
- The learning-record checker defines the plural language key used by its task.
- The course-expiry worker removes a hard-coded tenant filter, selects the
  correct template, resolves required user/course records, and honors the
  correctly named supervisor-disable setting.
- The root Composer lock updates eleven development/testing dependencies to
  patched releases after the pinned lock acquired seven published advisories.
  Runtime installation remains `--no-dev`; CI audits and tests the reviewed
  deployable lock.
- The IOMAD My Courses preference source and compiled AMD module keep
  tenant-aware tab preferences stable.
- The IOMAD Admin Tools block and full dashboard page accept
  capability-filtered, plugin-defined top-level tabs. Tenant Master uses this
  extension for the selected-company **Tenants** pane. Menu URLs are normalized
  before Mustache escaping so query parameters remain valid.

The exclusion manifest also removes
`admin/tool/mfa/factor/sms/db/hooks.php`. The pinned upstream file declares
SAML callbacks and an `auth_iomadsaml2` package despite residing in the MFA SMS
plugin, so retaining it after the SAML exclusion would register invalid hooks.
