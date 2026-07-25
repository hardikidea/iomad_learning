variable "aws_region" {
  type    = string
  default = "us-west-2"
}

variable "project_name" {
  type    = string
  default = "iomad-learning"
}

variable "vpc_cidr" {
  type    = string
  default = "10.41.0.0/16"
}

variable "public_subnet_cidrs" {
  type    = list(string)
  default = ["10.41.0.0/24", "10.41.1.0/24"]
}

variable "private_subnet_cidrs" {
  type    = list(string)
  default = ["10.41.10.0/24", "10.41.11.0/24"]
}

variable "allowed_cidr_blocks" {
  type    = list(string)
  default = ["0.0.0.0/0"]
}

variable "certificate_arn" {
  type    = string
  default = ""
}

variable "iomad_wwwroot" {
  type    = string
  default = ""
}

variable "image_tag" {
  type    = string
  default = "55b3128b8058d27f6cc4320850ca709ed5a792a9"
}

variable "container_repository_url" {
  type    = string
  default = "ghcr.io/hardikidea/iomadlearning"
}

variable "container_registry_credentials_secret_arn" {
  type    = string
  default = ""
}

variable "desired_count" {
  type    = number
  default = 2
}

variable "autoscaling_min_capacity" {
  type    = number
  default = null
}

variable "autoscaling_max_capacity" {
  type    = number
  default = null
}

variable "autoscaling_cpu_target" {
  type    = number
  default = 70
}

variable "autoscaling_memory_target" {
  type    = number
  default = 75
}

variable "cron_desired_count" {
  type    = number
  default = 1
}

variable "task_cpu" {
  type    = number
  default = 1024
}

variable "task_memory" {
  type    = number
  default = 2048
}

variable "database_instance_class" {
  type    = string
  default = "db.t4g.small"
}

variable "database_allocated_storage" {
  type    = number
  default = 100
}

variable "database_max_allocated_storage" {
  type    = number
  default = 250
}

variable "database_backup_retention_days" {
  type    = number
  default = 14
}

variable "database_deletion_protection" {
  type    = bool
  default = true
}

variable "database_skip_final_snapshot" {
  type    = bool
  default = false
}

variable "database_multi_az" {
  type    = bool
  default = true
}

variable "database_performance_insights_enabled" {
  type    = bool
  default = true
}

variable "redis_node_type" {
  type    = string
  default = "cache.t4g.small"
}

variable "redis_node_count" {
  type    = number
  default = 2
}

variable "enable_container_insights" {
  type    = bool
  default = true
}

variable "log_retention_days" {
  type    = number
  default = 30
}

variable "enable_cloudwatch_alarms" {
  type    = bool
  default = true
}

variable "alarm_sns_topic_arns" {
  type    = list(string)
  default = []
}

variable "route53_zone_id" {
  description = "Route53 hosted zone ID for the IOMAD DNS record. Leave empty to skip DNS."
  type        = string
  default     = ""
}

variable "route53_record_name" {
  description = "Fully qualified DNS record name for IOMAD. Leave empty to skip DNS."
  type        = string
  default     = ""
}

variable "route53_evaluate_target_health" {
  description = "Whether Route53 alias records should evaluate ALB target health."
  type        = bool
  default     = true
}

variable "route53_create_ipv6_record" {
  description = "Create an AAAA alias record in addition to the A alias record."
  type        = bool
  default     = false
}
