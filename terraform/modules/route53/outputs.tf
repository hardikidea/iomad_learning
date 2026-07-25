output "record_fqdn" {
  value = aws_route53_record.a.fqdn
}

output "record_name" {
  value = aws_route53_record.a.name
}

output "hosted_zone_id" {
  value = var.hosted_zone_id
}
