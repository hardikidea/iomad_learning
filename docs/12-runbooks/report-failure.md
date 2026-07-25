# Report Failure

1. Identify generation, export, storage, schedule, email, or tenant-scope
   failure from its stable category and request ID.
2. Confirm the requester capability and company/department scope before retry.
3. Delete incomplete temporary exports through the report plugin's API.
4. Retry asynchronous delivery only once per idempotency key.
5. Verify CSV/XLSX/PDF content contains only the authorized company set and no
   hidden cross-company rows or filters.

