# Cron Not Running

## Trigger

`cron` health fails or the last heartbeat exceeds the configured maximum age.

## Response

1. Confirm the dedicated cron service is running exactly one intended schedule.
2. Inspect failed scheduled and ad hoc tasks without printing task payloads.
3. Resolve database, Redis, storage, lock, or maintenance-mode blockers.
4. Run one foreground cron pass only after confirming another runner is not
   active.

## Verify

```bash
make cron
make health
```

Confirm queued messages, imports, completion, certificates, and license tasks
advance without duplicate execution.

