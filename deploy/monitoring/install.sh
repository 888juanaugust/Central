#!/usr/bin/env bash
#
# The monitoring stack, as root, after deploy/harden.sh (which installed
# Docker and rclone) and after .env beside this script is filled in:
#
#   sudo bash deploy/monitoring/install.sh
#
# Idempotent: creates the read-only monitor role once, the textfile
# directory, the allowlist when none exists, root's two cron lines, and
# brings the containers up (or up to date after an image bump).

set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$HERE/.env"

say() { printf '\n==> %s\n' "$*"; }

[ "$(id -u)" -eq 0 ] || { echo "Run as root (sudo)."; exit 1; }
[ -f "$ENV_FILE" ] || { echo "Copy $HERE/.env.example to $ENV_FILE and fill it in first."; exit 1; }
command -v docker >/dev/null || { echo "Docker is missing; run deploy/harden.sh first."; exit 1; }
# shellcheck disable=SC1090
. "$ENV_FILE"
: "${DOMAIN:?DOMAIN is not set in $ENV_FILE}"
: "${MONITOR_DB_PASSWORD:?MONITOR_DB_PASSWORD is not set in $ENV_FILE}"
: "${GF_SECURITY_ADMIN_PASSWORD:?GF_SECURITY_ADMIN_PASSWORD is not set in $ENV_FILE}"
case "$MONITOR_DB_PASSWORD$GF_SECURITY_ADMIN_PASSWORD" in *change-me*) echo "Replace the change-me passwords in $ENV_FILE."; exit 1 ;; esac

# --- a role that can only read ------------------------------------------------
say "PostgreSQL: the monitor role (SELECT on failed_jobs, backup_runs; pg_monitor for the exporter)"
if ! sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='monitor'" | grep -q 1; then
    sudo -u postgres psql -qc "CREATE ROLE monitor LOGIN PASSWORD '$MONITOR_DB_PASSWORD'"
else
    sudo -u postgres psql -qc "ALTER ROLE monitor WITH PASSWORD '$MONITOR_DB_PASSWORD'"
fi
sudo -u postgres psql -qd central <<'SQL'
GRANT pg_monitor TO monitor;
GRANT CONNECT ON DATABASE central TO monitor;
GRANT USAGE ON SCHEMA public TO monitor;
GRANT SELECT ON failed_jobs, backup_runs TO monitor;
SQL

# --- what node_exporter reads from files ------------------------------------
say "Textfile collector and the listening allowlist"
mkdir -p /var/lib/node_exporter/textfile /etc/central
[ -f /etc/central/listening-allowlist ] || install -m 0644 "$HERE/textfile/listening-allowlist.example" /etc/central/listening-allowlist
# Root runs copies it owns, never files the central user can edit: a cron line pointing
# into /srv/central would turn that account into root.
install -m 0755 "$HERE/textfile/collect.sh" /usr/local/sbin/central-collect
install -m 0755 "$HERE/ship-logs.sh" /usr/local/sbin/central-ship-logs
install -m 0600 "$ENV_FILE" /etc/central/monitoring.env
/usr/local/sbin/central-collect "$DOMAIN"

say "Root's cron: the collector every five minutes, the log shipper at 03:00"
cat > /etc/cron.d/central-monitoring <<CRON
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
*/5 * * * * root /usr/local/sbin/central-collect $DOMAIN >/dev/null 2>&1
0 3 * * * root /usr/local/sbin/central-ship-logs >> /var/log/central-ship-logs.log 2>&1
CRON
chmod 0644 /etc/cron.d/central-monitoring

# --- the containers ----------------------------------------------------------
say "docker compose up"
chmod 0600 "$ENV_FILE"
docker compose -f "$HERE/docker-compose.yml" --env-file "$ENV_FILE" pull -q
docker compose -f "$HERE/docker-compose.yml" --env-file "$ENV_FILE" up -d --remove-orphans
docker compose -f "$HERE/docker-compose.yml" --env-file "$ENV_FILE" ps

say "Up. Check (docs/BLUE-TEAM.md):"
cat <<NEXT
  1. https://monitor.$DOMAIN  (Caddy's password, then admin / GF_SECURITY_ADMIN_PASSWORD)
  2. Explore > Loki: {job="caddy"}, {job="journal"}, {job="central-security"} after one failed panel login
  3. Alerting > Contact points > owner-mail > Test: the mail arrives
  4. cat /var/lib/node_exporter/textfile/central.prom
  5. rclone ls $LOGS_RCLONE_REMOTE  after the first night, or  central-ship-logs  now
NEXT
