resource "aws_route53_record" "a" {
  zone_id = var.hosted_zone_id
  name    = var.record_name
  type    = "A"

  alias {
    name                   = var.target_dns_name
    zone_id                = var.target_zone_id
    evaluate_target_health = var.evaluate_target_health
  }
}

resource "aws_route53_record" "aaaa" {
  count = var.create_ipv6_record ? 1 : 0

  zone_id = var.hosted_zone_id
  name    = var.record_name
  type    = "AAAA"

  alias {
    name                   = var.target_dns_name
    zone_id                = var.target_zone_id
    evaluate_target_health = var.evaluate_target_health
  }
}
