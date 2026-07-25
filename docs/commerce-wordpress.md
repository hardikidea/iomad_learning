# Commerce And WordPress

## IOMAD Commerce

`local_iomadcommerce` manages company-scoped course products, orders and seat
assignments. Product states are `draft`, `free`, `paid` and `closed`. Paid
checkout redirects to a configured HTTPS provider; free checkout completes
locally. Access duration, recommendations and purchase history are stored
without handling card data.

Webhook requests require:

- a stable event ID and order ID;
- a company shortname and product external ID;
- a stable learner idnumber;
- a timestamp within the configured tolerance;
- an HMAC-SHA256 signature from a runtime secret;
- a supported transition (`paid`, `cancelled` or `refunded`).

Replay changes are rejected. Full cancellation/refund removes only enrolments
whose exact `user_enrolments.id` was created by this plugin. Existing manual,
license or other-plugin enrolments are preserved.

Bulk seat assignment accepts one `user_idnumber` column, rejects duplicates,
caps input at 10,000 and is resumable:

```bash
docker compose exec -T iomad \
  php public/local/iomadcommerce/cli/manage.php \
  --mode=assign-file --company=GV_SCHOOL --order=ORDER-EXTERNAL-ID \
  --users-file=/secure/users.csv
```

## WordPress Companion

The separately deployable package is
`commercial-integrations/wordpress/iomad-connect`. It:

- tests a tenant-restricted service connection;
- imports cursor-selected categories and courses;
- creates new WooCommerce products as drafts;
- creates federated IOMAD users without passwords;
- maps completed order item quantities to seats;
- handles cancelled orders and full refunds asynchronously;
- retries failed delivery with Action Scheduler or WordPress cron;
- supports product variations through parent course metadata.

Define secrets in `wp-config.php` or environment-backed constants, never the
WordPress options table:

```php
define('IOMAD_CONNECT_TOKEN', getenv('IOMAD_CONNECT_TOKEN'));
define('IOMAD_COMMERCE_WEBHOOK_SECRET', getenv('IOMAD_COMMERCE_WEBHOOK_SECRET'));
```

Subscription renewals that create completed WooCommerce renewal orders follow
the standard completed-order path. Membership visibility and
subscription-cancellation semantics vary by separately licensed extension and
must pass live acceptance before being advertised.

## Acceptance Boundary

Repository tests prove event contracts, signatures, retries, stable mapping,
idempotency and tenant scoping. Production acceptance additionally requires an
HTTPS WordPress deployment, a dedicated restricted service user, provider
sandbox credentials, webhook replay tests, full/partial refund tests and
extension-specific subscription/membership tests.
