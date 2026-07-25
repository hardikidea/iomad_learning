terraform {
  required_version = ">= 1.6.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = ">= 5.81.0, < 7.0.0"
    }
    random = {
      source  = "hashicorp/random"
      version = ">= 3.6.0, < 4.0.0"
    }
  }

  backend "s3" {}
}

provider "aws" {
  region = var.aws_region

  default_tags {
    tags = {
      Project     = var.project_name
      Environment = "stage"
      ManagedBy   = "terraform"
    }
  }
}

locals {
  route53_enabled         = var.route53_zone_id != "" && var.route53_record_name != ""
  route53_iomad_wwwroot   = local.route53_enabled ? "${var.certificate_arn != "" ? "https" : "http"}://${var.route53_record_name}" : ""
  effective_iomad_wwwroot = var.iomad_wwwroot != "" ? var.iomad_wwwroot : local.route53_iomad_wwwroot
}

module "iomad" {
  source = "../../modules/iomad_environment"

  project_name                              = var.project_name
  environment                               = "stage"
  aws_region                                = var.aws_region
  vpc_cidr                                  = var.vpc_cidr
  public_subnet_cidrs                       = var.public_subnet_cidrs
  private_subnet_cidrs                      = var.private_subnet_cidrs
  allowed_cidr_blocks                       = var.allowed_cidr_blocks
  certificate_arn                           = var.certificate_arn
  iomad_wwwroot                             = local.effective_iomad_wwwroot
  image_tag                                 = var.image_tag
  container_repository_url                  = var.container_repository_url
  container_registry_credentials_secret_arn = var.container_registry_credentials_secret_arn
  desired_count                             = var.desired_count
  autoscaling_min_capacity                  = var.autoscaling_min_capacity
  autoscaling_max_capacity                  = var.autoscaling_max_capacity
  autoscaling_cpu_target                    = var.autoscaling_cpu_target
  autoscaling_memory_target                 = var.autoscaling_memory_target
  cron_desired_count                        = var.cron_desired_count
  task_cpu                                  = var.task_cpu
  task_memory                               = var.task_memory
  database_instance_class                   = var.database_instance_class
  database_allocated_storage                = var.database_allocated_storage
  database_max_allocated_storage            = var.database_max_allocated_storage
  database_backup_retention_days            = var.database_backup_retention_days
  database_deletion_protection              = var.database_deletion_protection
  database_skip_final_snapshot              = var.database_skip_final_snapshot
  database_multi_az                         = var.database_multi_az
  database_performance_insights_enabled     = var.database_performance_insights_enabled
  redis_node_type                           = var.redis_node_type
  redis_node_count                          = var.redis_node_count
  enable_container_insights                 = var.enable_container_insights
  log_retention_days                        = var.log_retention_days
  enable_cloudwatch_alarms                  = var.enable_cloudwatch_alarms
  alarm_sns_topic_arns                      = var.alarm_sns_topic_arns
}

module "route53" {
  count = local.route53_enabled ? 1 : 0

  source = "../../modules/route53"

  hosted_zone_id         = var.route53_zone_id
  record_name            = var.route53_record_name
  target_dns_name        = module.iomad.alb_dns_name
  target_zone_id         = module.iomad.alb_zone_id
  evaluate_target_health = var.route53_evaluate_target_health
  create_ipv6_record     = var.route53_create_ipv6_record
}
