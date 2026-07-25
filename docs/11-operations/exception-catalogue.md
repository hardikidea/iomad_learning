# Exception Catalogue

The executable source is
[`exception_category.php`](../../iomad-overrides/public/admin/tool/iomadmonitor/classes/local/exception_category.php).
Operation code may supply only a trusted catalogue key. User input never
selects an exception class, status, runbook, or log field.

| Category | HTTP | Retry | Public detail |
|---|---:|---|---|
| `malformed_request` | 400 | no | generic |
| `authentication_required` | 401 | no | generic |
| `authorisation_denied`, `company_access_denied`, `tenant_resolution_failed` | 403 | no | generic |
| `resource_not_found`, `company_not_found`, `user_not_found`, `course_not_available` | 404 | no | generic |
| `method_not_allowed` | 405 | no | generic |
| `licence_unavailable`, `licence_expired`, `licence_conflict`, `enrolment_failed`, `payment_rejected` | 409 | no | generic |
| `unsupported_media_type` | 415 | no | generic |
| `validation_error` | 422 | no | generic |
| `rate_limited` | 429 | yes | generic |
| `external_response_invalid` | 502 | yes | generic |
| `database_unavailable`, `identity_provider_unavailable`, `payment_provider_unavailable`, `external_dependency_failed` | 503 | yes | generic |
| `external_timeout` | 504 | yes | generic |
| `completion_processing_failed`, `compliance_processing_failed`, `report_generation_failed`, `scheduled_task_failed` | 500 | bounded | hidden |
| `sso_configuration_error`, `configuration_error`, `internal_error` | 500 | no | hidden |

Problem responses contain `type`, generic `title`, `status`, stable `code`,
`retryable`, `correlation_id`, and backward-compatible `request_id`. They never
contain raw messages, traces, SQL, file paths, secrets, tenant names, email,
phone, chat addresses, payment data, or content.

The bounded `iomad_exception_total{category}` metric is held in application
cache and may reset on cache eviction or deployment. It is operational
telemetry, not an audit ledger.
