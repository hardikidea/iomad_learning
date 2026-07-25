# Licence Failure

1. Distinguish unavailable, expired, exhausted, and conflicting allocations.
2. Freeze automatic enrolment for the affected company/course pair.
3. Reconcile stable company, course, licence, and transaction references using
   IOMAD APIs and the project CLI; never edit allocation tables directly.
4. Preserve the audit trail and do not reuse an external transaction reference
   for different seat content.
5. Verify allocation, enrollment, expiry, suspension, and unrelated-company
   isolation with sanitized users.

