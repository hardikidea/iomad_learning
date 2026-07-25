# External Integration Failure

## Trigger

SSO, WordPress, commerce, email, WhatsApp, H5P, or SCORM integration errors
increase or their bounded queues become stale.

## Response

1. Identify the stable integration and error category using the request ID.
2. Verify endpoint allowlists, TLS, secret version, rate limits, and provider
   status. Never log tokens, addresses, bodies, or personal data.
3. Retry only idempotent operations through their queue or CLI resume mode.
4. Disable the optional integration if it threatens core learning availability.

## Verify

Use sanitized provider fixtures, verify replay rejection and tenant scope, then
confirm the integration queue returns to zero failed or stale records.

