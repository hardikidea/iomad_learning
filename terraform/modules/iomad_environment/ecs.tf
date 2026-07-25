resource "aws_cloudwatch_log_group" "iomad" {
  name              = "/ecs/${local.name_prefix}"
  retention_in_days = var.log_retention_days

  tags = local.common_tags
}

resource "aws_ecs_cluster" "iomad" {
  name = "${local.name_prefix}-cluster"

  setting {
    name  = "containerInsights"
    value = var.enable_container_insights ? "enabled" : "disabled"
  }

  tags = merge(local.common_tags, {
    Name = "${local.name_prefix}-cluster"
  })
}

resource "aws_ecs_task_definition" "iomad" {
  family                   = "${local.name_prefix}-task"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = var.task_cpu
  memory                   = var.task_memory
  execution_role_arn       = aws_iam_role.task_execution.arn
  task_role_arn            = aws_iam_role.task.arn

  runtime_platform {
    cpu_architecture        = "X86_64"
    operating_system_family = "LINUX"
  }

  volume {
    name = "iomaddata"

    efs_volume_configuration {
      file_system_id     = aws_efs_file_system.iomaddata.id
      transit_encryption = "ENABLED"

      authorization_config {
        access_point_id = aws_efs_access_point.iomaddata.id
        iam             = "ENABLED"
      }
    }
  }

  container_definitions = jsonencode([
    merge({
      name      = local.container_name
      image     = "${var.container_repository_url}:${var.image_tag}"
      essential = true

      portMappings = [
        {
          containerPort = local.container_port
          hostPort      = local.container_port
          protocol      = "tcp"
        }
      ]

      mountPoints = [
        {
          sourceVolume  = "iomaddata"
          containerPath = "/var/www/iomaddata"
          readOnly      = false
        }
      ]

      environment = local.iomad_environment
      secrets     = local.iomad_secrets

      linuxParameters = {
        initProcessEnabled = true
      }

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          awslogs-group         = aws_cloudwatch_log_group.iomad.name
          awslogs-region        = var.aws_region
          awslogs-stream-prefix = "iomad"
        }
      }
      }, var.container_registry_credentials_secret_arn == "" ? {} : {
      repositoryCredentials = {
        credentialsParameter = var.container_registry_credentials_secret_arn
      }
    })
  ])

  depends_on = [
    aws_efs_mount_target.iomaddata
  ]

  tags = local.common_tags
}

resource "aws_ecs_task_definition" "cron" {
  family                   = "${local.name_prefix}-cron"
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = 512
  memory                   = 1024
  execution_role_arn       = aws_iam_role.task_execution.arn
  task_role_arn            = aws_iam_role.task.arn

  runtime_platform {
    cpu_architecture        = "X86_64"
    operating_system_family = "LINUX"
  }

  volume {
    name = "iomaddata"

    efs_volume_configuration {
      file_system_id     = aws_efs_file_system.iomaddata.id
      transit_encryption = "ENABLED"

      authorization_config {
        access_point_id = aws_efs_access_point.iomaddata.id
        iam             = "ENABLED"
      }
    }
  }

  container_definitions = jsonencode([
    merge({
      name      = "cron"
      image     = "${var.container_repository_url}:${var.image_tag}"
      essential = true
      command   = ["iomad-cron-loop"]

      mountPoints = [
        {
          sourceVolume  = "iomaddata"
          containerPath = "/var/www/iomaddata"
          readOnly      = false
        }
      ]

      environment = local.iomad_environment
      secrets     = local.iomad_secrets

      linuxParameters = {
        initProcessEnabled = true
      }

      logConfiguration = {
        logDriver = "awslogs"
        options = {
          awslogs-group         = aws_cloudwatch_log_group.iomad.name
          awslogs-region        = var.aws_region
          awslogs-stream-prefix = "cron"
        }
      }
      }, var.container_registry_credentials_secret_arn == "" ? {} : {
      repositoryCredentials = {
        credentialsParameter = var.container_registry_credentials_secret_arn
      }
    })
  ])

  depends_on = [
    aws_efs_mount_target.iomaddata
  ]

  tags = local.common_tags
}

resource "aws_ecs_service" "iomad" {
  name                   = "${local.name_prefix}-service"
  cluster                = aws_ecs_cluster.iomad.id
  task_definition        = aws_ecs_task_definition.iomad.arn
  desired_count          = var.desired_count
  launch_type            = "FARGATE"
  enable_execute_command = true

  network_configuration {
    subnets          = values(aws_subnet.private)[*].id
    security_groups  = [aws_security_group.ecs.id]
    assign_public_ip = false
  }

  load_balancer {
    target_group_arn = aws_lb_target_group.iomad.arn
    container_name   = local.container_name
    container_port   = local.container_port
  }

  deployment_circuit_breaker {
    enable   = true
    rollback = true
  }

  depends_on = [
    aws_lb_listener.http_forward,
    aws_lb_listener.http_redirect,
    aws_lb_listener.https
  ]

  tags = local.common_tags
}

resource "aws_ecs_service" "cron" {
  name                   = "${local.name_prefix}-cron"
  cluster                = aws_ecs_cluster.iomad.id
  task_definition        = aws_ecs_task_definition.cron.arn
  desired_count          = var.cron_desired_count
  launch_type            = "FARGATE"
  enable_execute_command = true

  network_configuration {
    subnets          = values(aws_subnet.private)[*].id
    security_groups  = [aws_security_group.ecs.id]
    assign_public_ip = false
  }

  deployment_circuit_breaker {
    enable   = true
    rollback = true
  }

  tags = local.common_tags
}

resource "aws_appautoscaling_target" "iomad" {
  max_capacity       = local.autoscaling_max
  min_capacity       = local.autoscaling_min
  resource_id        = "service/${aws_ecs_cluster.iomad.name}/${aws_ecs_service.iomad.name}"
  scalable_dimension = "ecs:service:DesiredCount"
  service_namespace  = "ecs"
}

resource "aws_appautoscaling_policy" "cpu" {
  name               = "${local.name_prefix}-cpu"
  policy_type        = "TargetTrackingScaling"
  resource_id        = aws_appautoscaling_target.iomad.resource_id
  scalable_dimension = aws_appautoscaling_target.iomad.scalable_dimension
  service_namespace  = aws_appautoscaling_target.iomad.service_namespace

  target_tracking_scaling_policy_configuration {
    predefined_metric_specification {
      predefined_metric_type = "ECSServiceAverageCPUUtilization"
    }

    target_value = var.autoscaling_cpu_target
  }
}

resource "aws_appautoscaling_policy" "memory" {
  name               = "${local.name_prefix}-memory"
  policy_type        = "TargetTrackingScaling"
  resource_id        = aws_appautoscaling_target.iomad.resource_id
  scalable_dimension = aws_appautoscaling_target.iomad.scalable_dimension
  service_namespace  = aws_appautoscaling_target.iomad.service_namespace

  target_tracking_scaling_policy_configuration {
    predefined_metric_specification {
      predefined_metric_type = "ECSServiceAverageMemoryUtilization"
    }

    target_value = var.autoscaling_memory_target
  }
}
