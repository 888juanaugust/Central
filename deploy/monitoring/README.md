# Monitoring stack

Grafana, Loki, Prometheus, Alloy and three exporters on Central's own VPS, every
one on loopback, Grafana reached through Caddy at `monitor.<domain>`. The runbook
is [docs/BLUE-TEAM.md](../../docs/BLUE-TEAM.md); this page is the mechanics.

**Apply** (after `deploy/harden.sh <domain> <monitor-user>`):

```bash
cp deploy/monitoring/.env.example deploy/monitoring/.env   # then fill it in
sudo bash deploy/monitoring/install.sh
```

**Upgrade**: bump the image tags in `docker-compose.yml`, commit, deploy, then
`sudo bash deploy/monitoring/install.sh` again (it pulls and recreates).

**Verify**: `docker compose -f deploy/monitoring/docker-compose.yml ps` shows seven
services up; Explore in Grafana answers `{job="caddy"}`; a contact-point test mail
arrives; `cat /var/lib/node_exporter/textfile/central.prom` lists the two metrics.

**Files**

| Path | What |
|---|---|
| `docker-compose.yml` | the seven services, pinned, host network, loopback only |
| `.env.example` | the values `install.sh` and the containers read; copy to `.env` |
| `prometheus/prometheus.yml` | the scrape targets, 30 days kept |
| `loki/loki.yml` | one binary on the filesystem, 30 days kept |
| `alloy/config.alloy` | what is read and under which `job` label |
| `grafana/provisioning/` | datasources, the mail contact point, the policy, the alert rules |
| `grafana/dashboards/central-box.json` | the one dashboard |
| `textfile/collect.sh` | unexpected listening ports and certificate expiry, every five minutes |
| `textfile/listening-allowlist.example` | the baseline of sockets; copied to `/etc/central/listening-allowlist` |
| `ship-logs.sh` | yesterday's logs to the bucket, nightly at 03:00 |
| `install.sh` | the role, the directories, root-owned copies of the two scripts and of `.env`, the cron lines, `docker compose up` |
