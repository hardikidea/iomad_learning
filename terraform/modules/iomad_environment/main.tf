data "aws_availability_zones" "available" {
  state = "available"
}

locals {
  name_prefix = "${var.project_name}-${var.environment}"

  azs = slice(data.aws_availability_zones.available.names, 0, length(var.public_subnet_cidrs))

  common_tags = {
    Project     = var.project_name
    Environment = var.environment
    ManagedBy   = "terraform"
  }

  db_name          = "iomad"
  db_username      = "iomad"
  container_name   = "iomad"
  container_port   = 8080
  iomad_wwwroot    = var.iomad_wwwroot != "" ? var.iomad_wwwroot : "http://${aws_lb.iomad.dns_name}"
  use_ssl_proxy    = startswith(local.iomad_wwwroot, "https://")
  effective_scheme = local.use_ssl_proxy ? "HTTPS" : "HTTP"
  alarm_actions    = var.alarm_sns_topic_arns
  ok_actions       = var.alarm_sns_topic_arns
  autoscaling_min  = coalesce(var.autoscaling_min_capacity, var.desired_count)
  autoscaling_max  = coalesce(var.autoscaling_max_capacity, max(var.desired_count * 3, 2))

  iomad_environment = [
    { name = "IOMAD_DB_TYPE", value = "pgsql" },
    { name = "IOMAD_DB_HOST", value = aws_db_instance.iomad.address },
    { name = "IOMAD_DB_PORT", value = "5432" },
    { name = "IOMAD_DB_PREFIX", value = "mdl_" },
    { name = "IOMAD_WWWROOT", value = local.iomad_wwwroot },
    { name = "IOMAD_DATAROOT", value = "/var/www/iomaddata" },
    { name = "IOMAD_REVERSEPROXY", value = "true" },
    { name = "IOMAD_SSLPROXY", value = tostring(local.use_ssl_proxy) },
    { name = "IOMAD_COMPOSER_INSTALL", value = "false" },
    { name = "POSTGRES_DB", value = local.db_name },
    { name = "POSTGRES_USER", value = local.db_username },
    { name = "IOMAD_ADMIN_USER", value = "admin" },
    { name = "IOMAD_ADMIN_EMAIL", value = "admin@example.local" },
    { name = "IOMAD_SITE_FULLNAME", value = "IOMAD Learning ${title(var.environment)}" },
    { name = "IOMAD_SITE_SHORTNAME", value = "iomadlearning-${var.environment}" },
    { name = "IOMAD_REDIS_HOST", value = aws_elasticache_replication_group.redis.primary_endpoint_address },
    { name = "IOMAD_REDIS_PORT", value = "6379" },
    { name = "IOMAD_REDIS_TLS", value = "true" },
    { name = "IOMAD_REDIS_PREFIX", value = "${local.name_prefix}_session_" },
    { name = "TZ", value = "UTC" }
  ]

  iomad_secrets = [
    {
      name      = "POSTGRES_PASSWORD"
      valueFrom = "${aws_secretsmanager_secret.iomad.arn}:POSTGRES_PASSWORD::"
    },
    {
      name      = "IOMAD_ADMIN_PASSWORD"
      valueFrom = "${aws_secretsmanager_secret.iomad.arn}:IOMAD_ADMIN_PASSWORD::"
    }
  ]
}

resource "random_password" "database" {
  length           = 32
  special          = true
  override_special = "!#$%&*()-_=+[]{}<>:?"
}

resource "random_password" "admin" {
  length           = 24
  special          = true
  override_special = "!#$%&*()-_=+[]{}<>:?"
}

resource "random_id" "final_snapshot" {
  byte_length = 4

  keepers = {
    name_prefix = local.name_prefix
  }
}
