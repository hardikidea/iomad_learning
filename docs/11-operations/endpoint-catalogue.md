# Endpoint Catalogue

| Endpoint | Method | Authentication | Success | Failure | Data |
|---|---|---|---:|---:|---|
| `/health` | GET | none | 200 | connection failure | static liveness |
| `/health/live` | GET | none | 200 | connection failure | aggregate state and request ID |
| `/health/ready` | GET | none | 200 | 503 | aggregate readiness and request ID |
| `/health/startup` | GET | none | 200 | 503 | aggregate startup and request ID |
| `/health/metrics` | GET | bearer secret | 200 | 404 or 503 | bounded Prometheus metrics |
| `/admin/tool/iomadmonitor/` | GET | login and `tool/iomadmonitor:view` | 200 | Moodle access error | detailed checks |
| `/admin/tool/iomadmonitor/status.php` | GET | login and `tool/iomadmonitor:view` | 200 | Moodle access error | service catalogue |
| `/admin/tool/iomadmonitor/status.php?output=json` | GET | login and capability | 200 | Moodle access error | health and services |
| `/local/global_events/webhook.php` | POST | timestamped HMAC | 202 | 400 or 413 | fixed command acknowledgement |

## Rules

- Public health endpoints do not expose dependency names, tenant state, user
  data, configuration values, or exception messages.
- The metrics token is file-injected in Compose and must be held in Secrets
  Manager in AWS.
- Webhooks enforce a 32 KiB pre-bootstrap limit, signature freshness, replay
  claims, hashed chat addresses, exact company membership, fixed commands, and
  integer-only queued template variables.
- `X-Request-ID` is validated or generated. Valid W3C `traceparent` is preserved
  for repository-owned outbound calls; malformed context is replaced.

