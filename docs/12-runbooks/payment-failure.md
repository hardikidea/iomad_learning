# Payment Failure

## Trigger

Payment rejection, provider unavailability, stale orders, or webhook signature
failures increase.

## Response

1. Separate expected rejections from provider or application failures.
2. Preserve order idempotency and webhook replay claims.
3. Never expose payment credentials, provider payloads, or customer data.
4. Reconcile provider and LMS state before enrollment or refund retry.

## Verify

Use sandbox fixtures for approved providers, replay the same event, and confirm
one order transition, one enrollment decision, and company-scoped visibility.

