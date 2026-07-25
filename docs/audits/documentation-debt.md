# Documentation Debt

## Open Work

| Priority | Debt | Exit condition |
|---|---|---|
| High | Runtime acceptance evidence is blocked in the current sandbox | execute all Docker gates and publish immutable results |
| High | Canonical migration still has legacy root guides | migrate each row, preserve redirects, then mark complete |
| High | CODEOWNERS uses a repository-local account, not protected teams | platform owner supplies organization/team handles |
| High | Production alert receivers and escalation contacts are undefined | approved on-call route and tested notification |
| Medium | External links are not checked in offline CI | scheduled nonblocking external-link checker with allowlist |
| Medium | Mermaid syntax is fence-checked but not rendered in CI | pinned Mermaid CLI renders canonical diagrams |
| Medium | Browser evidence for mobile, RTL, reduced motion, and accessibility is pending | Playwright and axe artifacts pass |
| Medium | SLOs are engineering proposals | operations/product owners approve targets and budgets |
| Medium | Commercial provider capabilities require licensed staging plugins | compatibility, privacy, tenant, export, and upgrade tests |
| Low | Legacy ADR `001-product-suite-boundaries.md` has nonstandard numbering | preserve redirect and migrate to a four-digit ADR |
| Low | Cost and pricing documents are time-sensitive | owner and date revalidation outside engineering acceptance |

## Rules

Debt is not a pass. Every acceptance report must distinguish implemented,
validated, blocked, planned, rejected, and owner-review states.

