# Tenant Resolution Failure

1. Treat unknown, duplicate, or conflicting hostname/domain mappings as
   fail-closed.
2. Confirm the hostname, IOMAD company domain, parent/child relationship, and
   active membership through supported IOMAD administration interfaces.
3. Do not create a company automatically from an identity-provider attribute.
4. Purge caches after an approved mapping correction.
5. Smoke-test the default URL, affected hostname, and an unrelated hostname;
   cross-company content must remain inaccessible.

