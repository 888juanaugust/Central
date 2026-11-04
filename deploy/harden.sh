#!/usr/bin/env bash
#
# The defences of Central's server (Ubuntu 24.04), as root, after provision.sh
# and after a session as the central user is proven to work:
#
#   sudo bash deploy/harden.sh erp.example.co.id                 # no Grafana yet
#   sudo bash deploy/harden.sh erp.example.co.id grafana-owner   # also the monitor site
#   ADMIN_IP=203.0.113.7 sudo -E bash deploy/harden.sh ...       # never ban this address
#
# Idempotent: run it again after an interrupted attempt, after a new
# administrator address, after the kit changes. KEEP THIS SESSION OPEN and
# test a new one before closing it: this script locks root and passwords out.
# docs/BLUE-TEAM.md is the runbook this implements.

set -euo pipefail

DOMAIN="${1:?Usage: sudo bash deploy/harden.sh <domain> [monitor-user]}"
MONITOR_USER="${2:-}"
APP_DIR=/srv/central
APP_USER=central
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
KIT="$REPO_DIR/deploy"

say() { printf '\n==> %s\n' "$*"; }

# --- pre-flight --------------------------------------------------------------
[ "$(id -u)" -eq 0 ] || { echo "Run as root (sudo)."; exit 1; }
id "$APP_USER" >/dev/null 2>&1 || { echo "No $APP_USER user; run deploy/provision.sh first."; exit 1; }
if [ ! -s "/home/$APP_USER/.ssh/authorized_keys" ]; then
    echo "/home/$APP_USER/.ssh/authorized_keys is empty. Put your public key there and log in"
    echo "as $APP_USER once before running this: it switches passwords and root off."
    exit 1
fi
echo "Keep this session open until a NEW ssh session as $APP_USER works."

# --- sshd: keys only, one user ----------------------------------------------
say "sshd: keys only, no root, user $APP_USER"
install -m 0644 "$KIT/sshd/00-central.conf" /etc/ssh/sshd_config.d/00-central.conf
if [ -f /etc/ssh/sshd_config.d/50-cloud-init.conf ]; then
    # The image's file says PasswordAuthentication yes; sshd keeps the first
    # value it reads, so ours sorts first, and the old file goes aside anyway.
    mkdir -p /root/sshd-backup
    mv /etc/ssh/sshd_config.d/50-cloud-init.conf /root/sshd-backup/
fi
sshd -t
systemctl restart ssh

# --- patches: security updates apply themselves -----------------------------
say "unattended-upgrades: security origin, no automatic reboot"
apt-get update -q
apt-get install -yq unattended-upgrades needrestart
install -m 0644 "$KIT/apt/20auto-upgrades" /etc/apt/apt.conf.d/20auto-upgrades
install -m 0644 "$KIT/apt/50unattended-upgrades" /etc/apt/apt.conf.d/50unattended-upgrades
systemctl enable --now unattended-upgrades >/dev/null

# --- auditd: who touched what decides access ---------------------------------
say "auditd: the rules in deploy/auditd/central.rules"
apt-get install -yq auditd audispd-plugins
install -m 0640 "$KIT/auditd/central.rules" /etc/audit/rules.d/central.rules
augenrules --load >/dev/null
systemctl enable --now auditd >/dev/null

# --- crowdsec: parse, decide, ban at the firewall ----------------------------
say "CrowdSec: agent, firewall bouncer, the sshd, Caddy and Central scenarios"
if [ ! -f /etc/apt/keyrings/crowdsec.gpg ]; then
    mkdir -p /etc/apt/keyrings
    curl -fsSL https://packagecloud.io/crowdsec/crowdsec/gpgkey | gpg --dearmor -o /etc/apt/keyrings/crowdsec.gpg
fi
CODENAME="$(sed -n 's/^VERSION_CODENAME=//p' /etc/os-release)"
echo "deb [signed-by=/etc/apt/keyrings/crowdsec.gpg] https://packagecloud.io/crowdsec/crowdsec/ubuntu/ $CODENAME main" > /etc/apt/sources.list.d/crowdsec.list
apt-get update -q
# The iptables bouncer sits beside ufw; the nftables one would fight it.
apt-get install -yq crowdsec crowdsec-firewall-bouncer-iptables
cscli collections install crowdsecurity/sshd crowdsecurity/caddy crowdsecurity/linux crowdsecurity/http-cve --force >/dev/null
install -m 0644 "$KIT/crowdsec/acquis.d/central.yaml" /etc/crowdsec/acquis.d/central.yaml
install -D -m 0644 "$KIT/crowdsec/parsers/s01-parse/central-security-log.yaml" /etc/crowdsec/parsers/s01-parse/central-security-log.yaml
install -D -m 0644 "$KIT/crowdsec/scenarios/central-login-bf.yaml" /etc/crowdsec/scenarios/central-login-bf.yaml
if [ -n "${ADMIN_IP:-}" ]; then
    mkdir -p /etc/crowdsec/parsers/s02-enrich
    printf 'name: central/whitelist\ndescription: "The administrator'"'"'s own address is never banned"\nwhitelist:\n  reason: "Central administrator"\n  ip:\n    - "%s"\n' "$ADMIN_IP" > /etc/crowdsec/parsers/s02-enrich/central-whitelist.yaml
fi
# CrowdSec reads the Caddy and security logs as its own user: group-readable is enough.
usermod -aG caddy crowdsec 2>/dev/null || true
usermod -aG "$APP_USER" crowdsec 2>/dev/null || true
chmod 0750 "$APP_DIR/storage/logs" 2>/dev/null || true
systemctl enable --now crowdsec crowdsec-firewall-bouncer >/dev/null
systemctl restart crowdsec crowdsec-firewall-bouncer

# --- docker and rclone, for deploy/monitoring --------------------------------
say "Docker (published ports bound to loopback only) and rclone"
apt-get install -yq docker.io docker-compose-v2 rclone
mkdir -p /etc/docker
printf '{\n  "ip": "127.0.0.1",\n  "log-driver": "json-file",\n  "log-opts": { "max-size": "20m", "max-file": "3" }\n}\n' > /etc/docker/daemon.json
systemctl enable --now docker >/dev/null
systemctl restart docker

# --- caddy: the log mode, the monitor site ----------------------------------
say "Caddyfile ($DOMAIN${MONITOR_USER:+, monitor.$DOMAIN})"
if [ -n "$MONITOR_USER" ]; then
    echo "A password for $MONITOR_USER at monitor.$DOMAIN (Caddy asks for it before Grafana does):"
    MONITOR_HASH="$(caddy hash-password)"
    sed -e "s/__DOMAIN__/$DOMAIN/g" -e "s/__MONITOR_USER__/$MONITOR_USER/" -e "s|__MONITOR_HASH__|$MONITOR_HASH|" "$KIT/Caddyfile" > /etc/caddy/Caddyfile
else
    sed -e "s/__DOMAIN__/$DOMAIN/g" -e '/^# monitor-begin$/,/^# monitor-end$/d' "$KIT/Caddyfile" > /etc/caddy/Caddyfile
fi
install -m 0644 "$KIT/logrotate/central" /etc/logrotate.d/central
mkdir -p /var/log/caddy && chown caddy:caddy /var/log/caddy && chmod 0750 /var/log/caddy
caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null
systemctl reload caddy

say "Hardened. Still yours, in order (docs/BLUE-TEAM.md):"
cat <<NEXT
  1. In a NEW terminal:  ssh $APP_USER@$DOMAIN  must work and  ssh root@$DOMAIN  must be refused.
     Only then close this session.
  2. Hostinger panel, Firewall: allow 22 from your address, 80 and 443 from anywhere, nothing else.
  3. sudo cscli metrics       (three acquisitions reading)   sudo cscli decisions list
  4. sudo auditctl -l         (the rules loaded)              sudo unattended-upgrade --dry-run
  5. The monitoring stack:  sudo bash deploy/monitoring/install.sh
NEXT
