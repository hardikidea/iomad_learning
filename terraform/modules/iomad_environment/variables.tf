variable "project_name" {
  type        = string
  description = "Project name used as an AWS resource prefix."

  validation {
    condition     = can(regex("^[a-z][a-z0-9-]{2,31}$", var.project_name))
    error_message = "project_name must use lowercase letters, digits, and hyphens only."
  }
}

variable "environment" {
  type        = string
  description = "Environment name, for example dev, stage, or prod."
}

variable "aws_region" {
  type        = string
  description = "AWS region."
}

variable "vpc_cidr" {
  type        = string
  description = "CIDR block for the environment VPC."
}

variable "public_subnet_cidrs" {
  type        = list(string)
  description = "CIDR blocks for public ALB subnets."
}

variable "private_subnet_cidrs" {
  type        = list(string)
  description = "CIDR blocks for private ECS, RDS, Redis, and EFS subnets."
}

variable "allowed_cidr_blocks" {
  type        = list(string)
  description = "CIDR blocks allowed to reach the public load balancer."
  default     = ["0.0.0.0/0"]
}

variable "certificate_arn" {
  type        = string
  description = "Optional ACM certificate ARN. When set, an HTTPS listener is created."
  default     = ""
}

variable "iomad_wwwroot" {
  type        = string
  description = "Public IOMAD URL. Leave empty to use the ALB DNS name with HTTP."
  default     = ""
}

variable "image_tag" {
  type        = string
  description = "Immutable 40-character Git commit image tag to deploy."

  validation {
    condition     = can(regex("^[0-9a-f]{40}$", var.image_tag))
    error_message = "image_tag must be an immutable lowercase 40-character Git commit SHA."
  }
}

variable "container_repository_url" {
  type        = string
  description = "Container image repository URL without tag."
}

variable "container_registry_credentials_secret_arn" {
  type        = string
  description = "Optional Secrets Manager secret ARN with private registry credentials for ECS repositoryCredentials."
  default     = ""
}

variable "desired_count" {
  type        = number
  description = "Desired ECS task count."
}

variable "autoscaling_min_capacity" {
  type        = number
  description = "Minimum IOMAD web ECS service capacity. When null, desired_count is used."
  default     = null
}

variable "autoscaling_max_capacity" {
  type        = number
  description = "Maximum IOMAD web ECS service capacity. When null, max(desired_count * 3, 2) is used."
  default     = null
}

variable "autoscaling_cpu_target" {
  type        = number
  description = "Target average CPU utilization percentage for IOMAD web service autoscaling."
  default     = 70
}

variable "autoscaling_memory_target" {
  type        = number
  description = "Target average memory utilization percentage for IOMAD web service autoscaling."
  default     = 75
}

variable "cron_desired_count" {
  type        = number
  description = "Desired IOMAD cron ECS task count."
  default     = 1
}

variable "task_cpu" {
  type        = number
  description = "Fargate task CPU units."
}

variable "task_memory" {
  type        = number
  description = "Fargate task memory in MiB."
}

variable "database_instance_class" {
  type        = string
  description = "RDS PostgreSQL instance class."
}

variable "database_allocated_storage" {
  type        = number
  description = "Initial RDS storage in GiB."
}

variable "database_max_allocated_storage" {
  type        = number
  description = "RDS autoscaling storage ceiling in GiB."
}

variable "database_backup_retention_days" {
  type        = number
  description = "RDS backup retention period."
}

variable "database_deletion_protection" {
  type        = bool
  description = "Whether to enable RDS deletion protection."
}

variable "database_skip_final_snapshot" {
  type        = bool
  description = "Whether to skip the final RDS snapshot when the database is destroyed."
  default     = false
}

variable "database_multi_az" {
  type        = bool
  description = "Whether to enable RDS Multi-AZ for the IOMAD database."
  default     = false
}

variable "database_performance_insights_enabled" {
  type        = bool
  description = "Whether to enable RDS Performance Insights."
  default     = false
}

variable "redis_node_type" {
  type        = string
  description = "ElastiCache Redis node type."
}

variable "redis_node_count" {
  type        = number
  description = "Number of Redis cache nodes."
}

variable "enable_container_insights" {
  type        = bool
  description = "Enable ECS container insights."
  default     = true
}

variable "log_retention_days" {
  type        = number
  description = "CloudWatch log retention in days."
  default     = 30
}

variable "alarm_sns_topic_arns" {
  type        = list(string)
  description = "Optional SNS topic ARNs for CloudWatch alarm and OK actions."
  default     = []
}

variable "enable_cloudwatch_alarms" {
  type        = bool
  description = "Create baseline IOMAD CloudWatch alarms for ECS, ALB, RDS, and EFS."
  default     = true
}
