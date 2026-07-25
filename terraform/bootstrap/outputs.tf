output "state_bucket_name" {
  value = aws_s3_bucket.terraform_state.bucket
}

output "state_lock_table_name" {
  value = aws_dynamodb_table.terraform_locks.name
}

output "github_actions_role_arns" {
  value = {
    for environment, role in aws_iam_role.github_actions : environment => role.arn
  }
}
