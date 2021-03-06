resource "aws_lb_target_group" "actor" {
  name                 = "${local.environment}-actor"
  port                 = 80
  protocol             = "HTTP"
  target_type          = "ip"
  vpc_id               = data.aws_vpc.default.id
  deregistration_delay = 0
  depends_on           = [aws_lb.actor]
  tags                 = local.default_tags
}

resource "aws_lb" "actor" {
  name               = "${local.environment}-actor"
  internal           = false
  load_balancer_type = "application"
  subnets            = data.aws_subnet_ids.public.ids
  tags               = local.default_tags

  security_groups = [
    aws_security_group.actor_loadbalancer.id,
  ]

  access_logs {
    bucket  = data.aws_s3_bucket.access_log.bucket
    prefix  = "actor-${local.environment}"
    enabled = true
  }
}

resource "aws_lb_listener" "actor_loadbalancer" {
  load_balancer_arn = aws_lb.actor.arn
  port              = "443"
  protocol          = "HTTPS"
  ssl_policy        = "ELBSecurityPolicy-TLS-1-2-Ext-2018-06"

  certificate_arn = data.aws_acm_certificate.certificate_use.arn

  default_action {
    target_group_arn = aws_lb_target_group.actor.arn
    type             = "forward"
  }
}

resource "aws_security_group" "actor_loadbalancer" {
  name        = "${local.environment}-actor-loadbalancer"
  description = "Allow inbound traffic"
  vpc_id      = data.aws_vpc.default.id
  tags        = local.default_tags
}

resource "aws_security_group_rule" "actor_loadbalancer_ingress" {
  type              = "ingress"
  from_port         = 443
  to_port           = 443
  protocol          = "tcp"
  cidr_blocks       = module.whitelist.moj_sites
  security_group_id = aws_security_group.actor_loadbalancer.id
}

resource "aws_security_group_rule" "actor_loadbalancer_ingress_production" {
  count             = local.environment == "production" ? 1 : 0
  type              = "ingress"
  from_port         = 443
  to_port           = 443
  protocol          = "tcp"
  cidr_blocks       = ["0.0.0.0/0"]
  security_group_id = aws_security_group.actor_loadbalancer.id
}

resource "aws_security_group_rule" "actor_loadbalancer_egress" {
  type              = "egress"
  from_port         = 0
  to_port           = 0
  protocol          = "-1"
  cidr_blocks       = ["0.0.0.0/0"]
  security_group_id = aws_security_group.actor_loadbalancer.id
}

