# Compatibility Matrix

| Component | Supported | Repository default |
|---|---:|---|
| IOMAD series | 5.1 | `IOMAD_501_STABLE` |
| IOMAD commit | pinned | `55b3128b8058d27f6cc4320850ca709ed5a792a9` |
| Web root | `/public` | `/public` |
| PHP | 8.2-8.4 | `php:8.3-fpm-bookworm` |
| PostgreSQL | 15+ | `postgres:16-bookworm` |
| Redis | 7.x | `redis:7-bookworm` |
| Composer | 2.x | `composer:2.8.10` |
| Local SMTP | MailPit | `axllent/mailpit:v1.27.8` |
| `block_dash` | IOMAD/Moodle 5.1 | release `2.6` |
| `format_designer` | IOMAD/Moodle 5.1 | release `1.7` |
| `tool_courserating` | IOMAD/Moodle 4.5-5.2 | release `4.5.1` |
| `local_institutionpack` | IOMAD/Moodle 5.1 only | release `0.2.0` |
| `theme_iomad_learning` | IOMAD/Moodle 5.1 only | release `1.2.0` |
| `tool_iomadmonitor` | IOMAD/Moodle 5.1 only | release `1.2.0` |
| `local_global_events` | IOMAD/Moodle 5.1 only | release `0.3.0` beta |
| `local_iomad_h5p_bridge` | IOMAD/Moodle 5.1 only | release `0.1.0` beta |
| `local_iomad_scorm_gen` | IOMAD/Moodle 5.1 only | release `0.1.0` beta |
| `block_gamification_telemetry` | IOMAD/Moodle 5.1 only | release `0.2.0` beta |
| OpenTelemetry Collector | Optional local profile | `0.153.0` |
| Prometheus | Optional local profile | `3.12.0` |
| Grafana | Optional local profile | `13.1.0` |
| Loki | Optional local profile | `3.7.2` |
| Tempo | Optional local profile | `2.10.5` |
| Alertmanager | Optional local profile | `0.32.1` |
| Blackbox exporter | Optional local profile | `0.28.0` |

Any change to this matrix must update `versions.env`, `.env.example`, Docker Compose, CI build args, and upgrade documentation in the same pull request.

Every tracked override plugin must declare a `$plugin->supported` range containing `501`. `scripts/validate-plugin-compatibility.sh` enforces this rule.
