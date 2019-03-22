resource "aws_cloudwatch_log_group" "use-an-lpa" {
  name = "use-an-lpa"

  tags = "${merge(
      local.default_tags,
      map("Name", "use-an-lpa")
  )}"
}
