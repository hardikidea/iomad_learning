output "iomad_wwwroot" {
  value = module.iomad.iomad_wwwroot
}

output "alb_dns_name" {
  value = module.iomad.alb_dns_name
}

output "ecs_cluster_name" {
  value = module.iomad.ecs_cluster_name
}

output "ecs_service_name" {
  value = module.iomad.ecs_service_name
}

output "ecs_cron_service_name" {
  value = module.iomad.ecs_cron_service_name
}

output "database_endpoint" {
  value = module.iomad.database_endpoint
}

output "efs_file_system_id" {
  value = module.iomad.efs_file_system_id
}

output "cloudwatch_alarm_names" {
  value = module.iomad.cloudwatch_alarm_names
}

output "route53_record_fqdn" {
  value = try(module.route53[0].record_fqdn, null)
}
