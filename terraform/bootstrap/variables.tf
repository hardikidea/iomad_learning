variable "aws_region" {
  description = "AWS region for shared CI/CD bootstrap resources."
  type        = string
  default     = "us-west-2"
}

variable "project_name" {
  description = "Short project name used as an AWS resource prefix."
  type        = string
  default     = "iomad-learning"

  validation {
    condition     = can(regex("^[a-z][a-z0-9-]{2,31}$", var.project_name))
    error_message = "project_name must use lowercase letters, digits, and hyphens only."
  }
}

variable "github_repository" {
  description = "GitHub repository allowed to assume CI/CD roles, in owner/name form."
  type        = string
}

variable "environments" {
  description = "Deployment environments to create OIDC roles for."
  type        = set(string)
  default     = ["dev", "stage", "prod"]
}

variable "state_bucket_name" {
  description = "Optional explicit Terraform state bucket name. Leave null for an account/region-derived name."
  type        = string
  default     = null
}

variable "lock_table_name" {
  description = "Optional explicit Terraform state lock table name."
  type        = string
  default     = null
}
