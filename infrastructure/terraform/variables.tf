variable "hcloud_token" {
  sensitive   = true
  description = "Hetzner Cloud API Token"
}

variable "firewall_rules" {
  type = list(object({
    name        = string
    direction   = string
    protocol    = string
    port        = optional(string)
    source_ips  = list(string)
    description = optional(string)
  }))
  description = "List of firewall rules (ports to open)"
}
