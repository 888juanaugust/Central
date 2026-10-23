#!/usr/bin/env bash
#
# Yesterday's evidence, off the box: Caddy's access logs, Central's security
# log, the ssh and CrowdSec journal, the audit trail and CrowdSec's alerts,
# as one archive under logs/YYYY/MM/DD/ in the bucket, copied with an rclone
# remote whose key can write but not delete. Root's cron runs it at 03:00
# (install.sh); run it by hand when isolating an incident (docs/BLUE-TEAM.md):
#
#   bash deploy/monitoring/ship-logs.sh            # yesterday
#   bash deploy/monitoring/ship-logs.sh 2026-10-23 # one day

set -euo pipefail

# Root's copy of .env (install.sh writes it, root-only), else the one beside this script.
ENV_FILE=/etc/central/monitoring.env
[ -f "$ENV_FILE" ] || ENV_FILE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/.env"
# shellcheck disable=SC1090
[ -f "$ENV_FILE" ] && . "$ENV_FILE"
REMOTE="${LOGS_RCLONE_REMOTE:?LOGS_RCLONE_REMOTE is not set in $ENV_FILE}"

DAY="${1:-$(date -d yesterday +%F)}"
HOST="$(hostname -s)"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
STAGE="$WORK/$HOST-$DAY"
mkdir -p "$STAGE"

# Files, as they are: the day's rotated copies when they exist, else the live file.
for f in /var/log/caddy/central.log /var/log/caddy/monitor.log /var/log/audit/audit.log; do
    [ -f "$f" ] && cp --preserve=timestamps "$f" "$STAGE/" 2>/dev/null || true
done
for f in /var/log/caddy/*.log.1 /var/log/audit/audit.log.1; do
    [ -f "$f" ] && cp --preserve=timestamps "$f" "$STAGE/" 2>/dev/null || true
done
SECURITY="/srv/central/storage/logs/security-$DAY.log"
[ -f "$SECURITY" ] && cp --preserve=timestamps "$SECURITY" "$STAGE/"

# The journal and CrowdSec's own view of the day.
journalctl -u ssh -u crowdsec -u crowdsec-firewall-bouncer --since "$DAY 00:00:00" --until "$DAY 23:59:59" --no-pager -o short-iso > "$STAGE/journal.log" 2>/dev/null || true
journalctl _COMM=sudo --since "$DAY 00:00:00" --until "$DAY 23:59:59" --no-pager -o short-iso > "$STAGE/sudo.log" 2>/dev/null || true
command -v cscli >/dev/null && cscli alerts list --since 48h -o json > "$STAGE/crowdsec-alerts.json" 2>/dev/null || true
command -v cscli >/dev/null && cscli decisions list -o json > "$STAGE/crowdsec-decisions.json" 2>/dev/null || true

ARCHIVE="$WORK/$HOST-$DAY.tar.gz"
tar -C "$WORK" -czf "$ARCHIVE" "$(basename "$STAGE")"
sha256sum "$ARCHIVE" > "$ARCHIVE.sha256"

DEST="$REMOTE/logs/${DAY:0:4}/${DAY:5:2}/${DAY:8:2}"
rclone copy --no-traverse "$ARCHIVE" "$DEST"
rclone copy --no-traverse "$ARCHIVE.sha256" "$DEST"
echo "Shipped $(du -h "$ARCHIVE" | cut -f1) to $DEST"
