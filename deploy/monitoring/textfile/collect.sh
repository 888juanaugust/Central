#!/usr/bin/env bash
#
# Two metrics node_exporter cannot measure by itself, written every five
# minutes by root's cron (install.sh) for its textfile collector:
#
#   central_unexpected_listening_port{proto,addr,port,process}  1 per socket not on the allowlist
#   central_tls_cert_expiry_seconds                             seconds until the site's certificate ends
#
# The allowlist is /etc/central/listening-allowlist: one "proto addr:port"
# per line (listening-allowlist.example is the baseline of a Central box).
# The file is written whole and renamed, so the collector never reads half.

set -euo pipefail

ALLOWLIST=/etc/central/listening-allowlist
OUT_DIR=/var/lib/node_exporter/textfile
OUT="$OUT_DIR/central.prom"
DOMAIN="${1:-}"

mkdir -p "$OUT_DIR"
TMP="$(mktemp "$OUT_DIR/.central.XXXXXX")"
trap 'rm -f "$TMP"' EXIT

{
    echo '# HELP central_unexpected_listening_port A listening socket that is not on /etc/central/listening-allowlist.'
    echo '# TYPE central_unexpected_listening_port gauge'
    # ss -H: no header; -l listening; -t tcp; -u udp; -n numeric; -p the process.
    ss -Hltunp 2>/dev/null | while read -r proto _ _ _ local _ rest; do
        addr="${local%:*}"
        port="${local##*:}"
        # IPv6 wildcard and IPv4 wildcard are the same door.
        case "$addr" in '*'|'[::]'|'0.0.0.0'|'::') addr='*' ;; esac
        [ -s "$ALLOWLIST" ] && grep -qxF "$proto $addr:$port" "$ALLOWLIST" && continue
        process="$(printf '%s' "$rest" | sed -n 's/.*users:(("\([^"]*\)".*/\1/p')"
        printf 'central_unexpected_listening_port{proto="%s",addr="%s",port="%s",process="%s"} 1\n' "$proto" "$addr" "$port" "${process:-?}"
    done

    if [ -n "$DOMAIN" ]; then
        echo '# HELP central_tls_cert_expiry_seconds Seconds until the certificate served for the site expires.'
        echo '# TYPE central_tls_cert_expiry_seconds gauge'
        NOT_AFTER="$(echo | openssl s_client -servername "$DOMAIN" -connect 127.0.0.1:443 2>/dev/null | openssl x509 -noout -enddate 2>/dev/null | sed 's/^notAfter=//')"
        if [ -n "$NOT_AFTER" ]; then
            END="$(date -d "$NOT_AFTER" +%s)"
            printf 'central_tls_cert_expiry_seconds{domain="%s"} %s\n' "$DOMAIN" "$((END - $(date +%s)))"
        fi
    fi
} > "$TMP"

chmod 0644 "$TMP"
mv -f "$TMP" "$OUT"
trap - EXIT
