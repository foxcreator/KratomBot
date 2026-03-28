hcloud_token = "FJjQhhm4HDor4zOLTvVDvYev8q9hS0Lc1MTapCjurTlLt0lqZHIv3n54SSqcKrqv"

firewall_rules = [
  {
    name      = "ssh"
    direction = "in"
    protocol  = "tcp"
    port      = "22"
    source_ips = ["0.0.0.0/0"]
  },
  {
    name      = "https"
    direction = "in"
    protocol  = "tcp"
    port      = "443"
    source_ips = ["0.0.0.0/0"]
  },
  {
    name      = "http"
    direction = "in"
    protocol  = "tcp"
    port      = "80"
    source_ips = ["0.0.0.0/0"]
  }
]
