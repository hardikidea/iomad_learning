# Prompt Conflict Report

| Prompt request | Conflict | Decision |
|---|---|---|
| `floci/floci:latest` and UI `latest` | repository forbids floating deployment images | retain reviewed version+digest pins |
| generic Bitnami Moodle runtime | baseline requires official pinned IOMAD source and project overrides | retain custom IOMAD image |
| direct block/config/database injection | violates Moodle/IOMAD API and upgrade boundaries | use plugin services and Moodle APIs |
| automatic SQL company-clause interception | cannot prove semantic tenant safety and can corrupt valid core queries | reject; enforce scope in repositories/services |
| add database indexes directly to upstream tables | upstream schema ownership and upgrade conflict | reject until measured and contributed/upstream-safe |
| store per-tenant Stripe secrets in company rows | secret exposure and key-rotation failure | use approved secret provider references |
| duplicate asynchronous certificate/PDF engine | official IOMAD certificate module already owns issuing and rendering | integrate official records, do not fork |
| send certificate codes through chat | creates disclosure and account-linking risk | send count only and direct to authenticated IOMAD |
| full automatic tracing of Moodle/SQL | privacy, cardinality, and performance risk | bounded repository-owned spans only |
| canvas/confetti/fireball effects | accessibility and maintainability conflict | reduced-motion-aware DOM feedback only |
| implement every commercial feature label as an unrestricted clone | licensing, scope, and unsupported-claim risk | implement maintainable project capabilities and document admission |
| execute workbook macros in automation | production/CI code-execution risk | generate interfaces only; canonical source remains CSV/YAML |

Prompt text remains an input to engineering review. Repository invariants,
security controls, verified upstream behavior, and executable acceptance tests
take precedence over unsafe or contradictory examples.

