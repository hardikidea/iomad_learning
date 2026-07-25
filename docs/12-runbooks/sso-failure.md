# SSO Failure

1. Keep password-based break-glass administration restricted and audited.
2. Identify whether configuration, tenant-domain resolution, certificate,
   provider availability, clock skew, or attribute mapping failed.
3. Verify metadata/signing material from the approved secret/configuration
   source. Do not paste assertions or tokens into logs.
4. Test one sanitized user for the affected company and one unrelated company
   to prove mapping isolation.
5. Restore normal routing only after login, logout, disabled-user, and wrong
   tenant-domain cases pass.

