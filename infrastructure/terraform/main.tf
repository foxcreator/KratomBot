terraform {
  required_version = "= 1.14.3"
  
  backend "s3" {
    bucket = "kratombot-infrastructure"
    key    = "terraform/terraform.tfstate"
    region = "eu-north-1"
  }
  
  required_providers {
    hcloud = {
      source  = "hetznercloud/hcloud"
      version = "=1.45.0"
    }
  }
}

provider "hcloud" {
  token = var.hcloud_token
}

# SSH Key
resource "hcloud_ssh_key" "default" {
  name       = "kratombot-key"
  public_key = file("${path.module}/files/tf_hetzner.pub")
}

# Firewall
resource "hcloud_firewall" "web" {
  name  = "kratombot-firewall"

  dynamic "rule" {
    for_each = var.firewall_rules
    content {
      direction   = rule.value.direction
      protocol    = rule.value.protocol
      port        = try(rule.value.port, null)
      source_ips  = rule.value.source_ips
      description = try(rule.value.description, null)
    }
  }
}

# Server (Autonomous / No Network)
resource "hcloud_server" "web" {
  name               = "kratombot-web"
  image              = "ubuntu-24.04"
  server_type        = "cx23"
  ssh_keys           = [hcloud_ssh_key.default.id]
  keep_disk          = true
  rebuild_protection = true
  delete_protection  = true
  backups            = true
  firewall_ids       = [hcloud_firewall.web.id]
  
  labels = {
    "Environment" = "production"
    "Project"     = "kratombot"
    "Public"      = "true"
  }

  public_net {
    ipv4_enabled = true
    ipv6_enabled = false
  }
}

# Output IP for Ansible Inventory
output "server_ip" {
  value = hcloud_server.web.ipv4_address
}
