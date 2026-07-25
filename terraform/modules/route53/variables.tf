variable "hosted_zone_id" {
  description = "Route53 hosted zone ID where the IOMAD DNS record will be created."
  type        = string
}

variable "record_name" {
  description = "Fully qualified record name, for example iomad-dev.example.com."
  type        = string
}

variable "target_dns_name" {
  description = "DNS name of the target load balancer."
  type        = string
}

variable "target_zone_id" {
  description = "Canonical hosted zone ID of the target load balancer."
  type        = string
}

variable "evaluate_target_health" {
  description = "Whether Route53 should evaluate target health for the alias record."
  type        = bool
  default     = true
}

variable "create_ipv6_record" {
  description = "Create an AAAA alias record in addition to the A alias record."
  type        = bool
  default     = false
}
