=== IOMAD Connect ===
Contributors: iomad-learning
Requires at least: 6.5
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv3 or later

Tenant-scoped IOMAD catalogue, federated user, and WooCommerce synchronization.

== Security ==

Define both secrets in wp-config.php or inject equivalent environment-backed constants:

define('IOMAD_CONNECT_TOKEN', getenv('IOMAD_CONNECT_TOKEN'));
define('IOMAD_COMMERCE_WEBHOOK_SECRET', getenv('IOMAD_COMMERCE_WEBHOOK_SECRET'));

The plugin never synchronizes Moodle or WordPress passwords. Configure both applications
against the same OpenID Connect provider for single sign-on.

== Features ==

* Restricted Moodle web-service connection test.
* Cursor-based selective category and course synchronization.
* Courses imported as draft WooCommerce products.
* Federated IOMAD user provisioning before purchase fulfillment.
* Signed, replay-safe paid, cancelled, and full-refund callbacks.
* Bulk copy quantities mapped to IOMAD commerce seats.
* Translation-ready WordPress strings.

== Operational notes ==

The IOMAD Connect web service is disabled by default and restricted to explicitly
authorized service users. Enable it only after creating a tenant-scoped service user.
Partial WooCommerce refunds do not revoke IOMAD access; only full refunds do.
