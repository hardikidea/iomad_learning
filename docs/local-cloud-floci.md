# Local AWS Emulation With Floci

This runbook describes the local AWS-compatible environment for IOMAD. Floci
is used only for development and integration tests. Production remains AWS
ECS, RDS, EFS, ElastiCache, Secrets Manager, and CloudWatch as defined under
`terraform/`.

## Repository Analysis

The production Terraform dependency path is:

```text
ALB -> ECS web task -> RDS PostgreSQL 16
                    -> ElastiCache Redis 7.1
                    -> EFS /var/www/iomaddata
                    -> Secrets Manager
    -> ECS cron task -> same database, cache, dataroot, and image
```

| Concern | Existing production declaration | Local Floci mapping |
| --- | --- | --- |
| Database | RDS PostgreSQL 16, encrypted, private, backed up | Real `postgres:16-bookworm` child container through RDS proxy port `7001` |
| Sessions/cache | ElastiCache Redis 7.1 with in-transit and at-rest encryption | Real `redis:7-bookworm` child container through ElastiCache proxy port `16380` |
| Dataroot | Encrypted EFS access point and backup policy | Named `iomaddata` Docker volume |
| Secrets | Secrets Manager secret for database/admin credentials | Secrets Manager plus ignored mode-`0600` local env files |
| Object storage | S3 exists only for Terraform state | Versioned local files and recovery-set buckets |
| Email API | No SES resource in production Terraform | Verified local SES identity for integration development |
| Parameters | No application SSM parameters | Namespaced local connection and bucket parameters |

### Validated Gaps

1. Production Terraform has no application S3 buckets, SES identities, or
   application SSM parameters.
2. The ECS task role grants EFS and ECS Exec access, but no S3 object, SSM
   parameter, or SES send permissions.
3. Core `repository_s3` is an external course-file picker. It is not a
   replacement for `moodledata/filedir`, and this repository does not include
   an unreviewed object-storage plugin. EFS remains the supported production
   dataroot until a compatible alternative-file-system plugin is admitted and
   tested.
4. Redis endpoints were present in ECS but were not consumed by `config.php`.
   The tracked configuration now enables Redis sessions and TLS verification
   when `IOMAD_REDIS_HOST` and `IOMAD_REDIS_TLS=true` are supplied.
5. AWS-facing project names now use `iomad-learning`; Terraform validation
   rejects underscores before derived S3, RDS, or ALB names can reach AWS.
   The local provisioner also normalizes explicit legacy overrides.

## Docker Architecture

[`docker-compose.local.yml`](../docker-compose.local.yml) contains exactly
four services:

```yaml
services:
  floci:       # Layer 1: AWS API and real Docker-backed RDS/ElastiCache
  floci-ui:    # Layer 2: management console on 127.0.0.1:4500
  iomad-php:   # Layer 3A: PHP-FPM with the pinned IOMAD image
  web:         # Layer 3B: Nginx using /var/www/iomad/public

volumes:       # Layer 4: local storage and persistence
  floci_state:
  iomaddata:
```

Floci receives `/var/run/docker.sock` and the explicit
`iomad_learning_floci` network, allowing it to start real PostgreSQL and Redis
children. API and proxy ports bind to `127.0.0.1` only. Floci metadata,
`iomaddata`, and child database/cache volumes survive `docker compose down`;
do not add `--volumes` unless intentionally deleting local state.

Floci derives major-version runtime aliases such as `postgres:16-bookworm`
from the requested AWS engine version. Before Floci starts, the provisioner
pulls the reviewed patch versions and points those local aliases at the
reviewed images. This prevents a fresh Floci child from resolving a newer
floating image unexpectedly.

Reviewed emulator images are pinned in `versions.env`:

```dotenv
FLOCI_IMAGE=floci/floci:1.5.33@sha256:d2ecc8035822b23b8587a56eab15edd825f41d3fb80d93e8e66680410beddc08
FLOCI_UI_IMAGE=floci/floci-ui:0.2.0@sha256:03a261144e0708993c8e48b763a0edb072415feae4325f254beeb1835fa424d9
AWS_CLI_IMAGE=amazon/aws-cli:2.36.7@sha256:5b76c069e37cfa091ec6398dc683c09e0c9ef8ae2e557b0a36d931df34011227
FLOCI_POSTGRES_IMAGE=postgres:16.14-bookworm
FLOCI_REDIS_IMAGE=redis:7.4.9-bookworm
```

The Docker socket is equivalent to host-level container control. Run this
stack only on a trusted developer machine and never expose Floci or its UI on
a shared network.

## Shell Provisioning

[`init-local-cloud.sh`](../init-local-cloud.sh) reads scalar defaults and
overrides from `terraform/envs/<environment>`, normalizes AWS identifiers, and
uses the standard AWS CLI with the Floci endpoint:

```bash
aws --endpoint-url=http://localhost:4566 --region=us-west-2 \
  s3api create-bucket --bucket <normalized-bucket>

aws --endpoint-url=http://localhost:4566 --region=us-west-2 \
  rds create-db-instance --engine postgres --engine-version 16 ...

aws --endpoint-url=http://localhost:4566 --region=us-west-2 \
  elasticache create-replication-group --engine redis ...
```

When the host has no AWS CLI, the pinned `amazon/aws-cli` container runs on
the same Docker network and uses `http://floci:4566`. No production AWS
credentials are read or required.

Commands:

```bash
# Validate the exact architecture without provisioning resources.
make local-cloud-validate

# Provision AWS APIs and real database/cache children only.
make local-cloud-provision

# Provision and start IOMAD without installing an empty database.
make local-cloud-init

# Provision, start, and install IOMAD when the database is empty.
make local-cloud-install

# Import sanitized school and university packs through local_institutionpack.
make local-cloud-demo-data

make local-cloud-status
make local-cloud-cron
make local-cloud-down
```

The process is idempotent. It creates versioned/private S3 buckets, SSM
parameters, a Secrets Manager secret, a least-privilege local task policy, an
SES identity, RDS, and ElastiCache. It then validates an S3 write/read loop,
`SELECT 1` through the RDS proxy, and `PONG` through the Redis proxy.

Floci 1.5.x restores RDS from its persistent child volume. Its persisted
ElastiCache object can occasionally return `available` before a Redis child
exists after a control-plane recreation. The provisioner detects this
metadata/wire-state mismatch and recreates only the disposable cache; it
never recreates or downgrades an unavailable RDS database.

Local customization belongs in `.env.local`, which is ignored:

```dotenv
AWS_REGION=us-west-2
TF_ENVIRONMENT=dev
IOMAD_LOCAL_HTTP_PORT=18081
IOMAD_LOCAL_WWWROOT=http://localhost:18081
IOMAD_NOREPLY_ADDRESS=noreply@example.com
IOMAD_DISABLE_EMAIL=true
```

Database and administrator passwords are generated into
`.env.local.secrets`; the application environment is generated into
`.env.local.runtime`. Both are ignored and mode `0600`. The script never
prints either value.

Moodle delivery is disabled in this four-service profile because it does not
add a fifth SMTP container. SES API calls remain available at Floci for
project integrations. Use the main `docker-compose.yml` MailPit profile when
testing Moodle SMTP delivery.

## PHP Configuration Overrides

The tracked [`docker/iomad/config.php`](../docker/iomad/config.php) consumes
only environment variables:

```php
$CFG->session_handler_class = '\core\session\redis';
$CFG->session_redis_host = getenv('IOMAD_REDIS_HOST');
$CFG->session_redis_port = (int)getenv('IOMAD_REDIS_PORT');

$CFG->local_aws_s3_client_options = [
    'version' => 'latest',
    'region' => getenv('AWS_REGION'),
    'endpoint' => rtrim(getenv('IOMAD_AWS_ENDPOINT'), '/'),
    'use_path_style_endpoint' => true,
];
```

`skip_requesting_account_id = true` is a HashiCorp AWS provider argument, not
an AWS SDK for PHP S3 client option. Passing it to the PHP SDK would be
invalid. The correct local Terraform provider block is:

```hcl
provider "aws" {
  region                      = "us-west-2"
  access_key                  = "test"
  secret_key                  = "test"
  skip_credentials_validation = true
  skip_metadata_api_check     = true
  skip_requesting_account_id  = true
  s3_use_path_style           = true

  endpoints {
    s3  = "http://localhost:4566"
    rds = "http://localhost:4566"
    # See terraform/local/floci-provider.tf.example for all services.
  }
}
```

The `$CFG->local_aws_*` values are a project-owned integration contract for
supported plugins. They do not silently redirect Moodle file storage. Any
future S3 file-system integration must pass plugin compatibility, tenant
isolation, clean-install, upgrade, backup, and restore tests before admission.

## Persistence And Recovery

Local Floci metadata and `iomaddata` are persistent named volumes. Floci
creates additional namespaced volumes for its real RDS and ElastiCache child
containers. This is useful development persistence, not an AWS backup
simulation.

Production recovery remains a matching set:

```text
immutable application image + RDS snapshot + EFS recovery point + manifest
```

Do not use a local Floci snapshot as proof of the production RDS/EFS restore
pipeline. The repository backup and restore workflows remain the acceptance
path for production recovery.

## Endpoints

| Service | Local endpoint |
| --- | --- |
| Floci AWS API | `http://localhost:4566` |
| Floci UI | `http://localhost:4500` |
| IOMAD | `http://localhost:18081` |
| RDS proxy | `localhost:7001` initially |
| ElastiCache proxy | `localhost:16380` initially |

Proxy ports are assigned from configured ranges. Consume the generated
`.env.local.runtime` or query the Floci APIs instead of assuming the first
port in automation.

## References

- Floci documentation: <https://floci.io/floci/>
- Docker-backed services: <https://floci.io/floci/configuration/docker/>
- RDS emulation: <https://floci.io/floci/services/rds/>
- ElastiCache emulation: <https://floci.io/floci/services/elasticache/>
- Persistent storage: <https://floci.io/floci/configuration/storage/>
