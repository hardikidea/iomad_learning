# ADR-0003: Operability And Gamification Boundaries

## Status

Accepted

## Context

The consolidated product prompts request health endpoints, distributed
telemetry, cross-tenant global events, interactive SCORM/H5P rewards,
role-aware dashboards, external messaging, and demo automation. Several
example prompts also propose direct core-table writes, session-derived tenant
IDs, untrusted SQL rewriting, and production macro execution.

## Decision Drivers

- Reliability: monitoring failures must not take down learning workflows.
- Security: tenant scope must be explicit and server-derived.
- Upgradeability: official IOMAD and Moodle behavior must remain upstream.
- Auditability: rewards and messages require immutable idempotency keys.
- Privacy: telemetry must exclude content, credentials, and personal data.

## Considered Options

### Patch Upstream IOMAD And Core Tables

- Pros: direct access to internals.
- Cons: unsafe tenant boundaries, fragile upgrades, and unsupported rollback.

### Repository-Owned Plugins Over Supported APIs

- Pros: versioned ownership, install/upgrade hooks, focused tests, and clear
  privacy contracts.
- Cons: requires adapters and explicit compatibility validation.

## Decision

Implement repository-owned Moodle plugins under `iomad-overrides/`. Use IOMAD
membership APIs and Moodle event, enrolment, grade, badge, and message APIs.
Persist only plugin-owned state directly. Keep the operations monitor
independent of business workflows and make telemetry export fail-open.

## Consequences

### Positive

- Official IOMAD remains reproducible and upgradeable.
- Company scope can be tested at repository boundaries.
- Reward and messaging operations are idempotent and auditable.
- External vendors remain optional adapters.

### Negative

- Requested commercial behavior cannot be claimed merely from a feature label.
- Browser, failure, privacy, and upgrade tests are required before acceptance.

### Risks And Mitigations

- Risk: duplicate learning events grant points twice.
- Mitigation: immutable company-scoped idempotency keys.
- Risk: a parent manager obtains learner-level data from a child company.
- Mitigation: aggregate-only parent views and explicit capability tests.
- Risk: telemetry exposes personal data.
- Mitigation: allowlisted fields, redaction, bounded labels, and payload tests.

## Implementation Notes

- Runtime: `tool_iomadmonitor`, `local_global_events`,
  `local_iomad_scorm_gen`, `local_iomad_h5p_bridge`, and
  `block_gamification_telemetry`.
- Pipeline: install/upgrade, PHPUnit, cross-company denial, package validation,
  observability configuration validation, and failure drills.
- Runbook: health, queue, collector, Redis, database, and recovery procedures.

## Related Decisions

- [ADR-0001](0001-aws-iomad-target-architecture.md)
- [ADR-0002](0002-github-actions-oidc-ghcr-delivery.md)
