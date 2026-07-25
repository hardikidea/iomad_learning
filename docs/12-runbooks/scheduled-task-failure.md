# Scheduled Task Failure

## Trigger

Failed ad hoc tasks are nonzero, an allowlisted task repeatedly fails, or a
queue remains stale.

## Response

1. Record task class, component, stable category, attempt count, and request ID.
2. Redact task arguments and user or tenant data.
3. Resolve the dependency before retrying. Keep retries bounded and
   idempotent.
4. Quarantine a poison item rather than blocking unrelated queue records.

## Verify

Run the task in a disposable environment, confirm successful state transition,
then run it again to prove idempotency.

