# Defending the server

One VPS runs the books, so one VPS is what an attacker wants. This page is the runbook
of sub-project 7 (`docs/superpowers/specs/2026-10-23-blue-team-design.md`): what is
watched, where an alert goes, how to apply the kit, what to check each month and each
quarter, and what to do the day something is wrong. [DEPLOY.md](DEPLOY.md) is the
server itself; this page is its defence.

Two limits, stated once. The logs live on the box that is attacked, so a copy of each day
leaves it every night with a key that can write but not delete; what an attacker erases
after that is still in the bucket. And a box cannot report that it is gone, so one
external HTTPS check (a free uptime service of your choice, watching `https://<domain>/up`
and the certificate) is the only alert that survives the box being switched off. Set it
up by hand; nothing in the kit can.

## 1. What is watched

| Signal | Where it is seen | Who is told |
|---|---|---|
| A password guessed at SSH | CrowdSec (`cscli decisions list`), the journal in Grafana | banned at the firewall; a spike mails the owner |
| A password guessed at the panel or the portal | the Activity Log, the Operations screen, the security log in Grafana | Filament refuses after five a minute; CrowdSec bans after ten in ten minutes; the watch mails and, when configured, WhatsApps the administrators |
| An SSH session opened, `sudo` used | the journal in Grafana | the owner, by mail, every time |
| `.env`, sshd, sudoers or the identity files written | auditd (`ausearch -k central-env`, `sshd`, `sudoers`, `identity`) | the owner, by mail, at once |
| A port listening that the baseline does not know | `central_unexpected_listening_port` from `collect.sh` | the owner, by mail, after five minutes |
| An administrator signing in from an address not seen in ninety days | the Operations screen, the security watch | the administrators (mail, WhatsApp) |
| Rights or memberships changed, a user made administrator | the Activity Log, the security watch | the administrators |
| Segregation of Duties switched off | the Activity Log, the security watch | the administrators |
| A price list published over the safety brake | the Activity Log, the security watch | the administrators |
| A posted receipt's amount, date or customer changed | the Activity Log, the security watch | the administrators |
| A receipt or invoice deleted outside business hours | the Activity Log, the security watch | the administrators |
| CPU above 90 % for thirty minutes, outbound traffic above 20 MB/s for ten | Grafana | the owner, by mail |
| Disk above 80 %, the certificate within fourteen days of expiry | Grafana | the owner, by mail |
| The queue above 200 jobs, any failed job, no verified off-site backup for 26 hours | Grafana and, independently, `central:health` | the owner by Grafana; the administrators by the hourly sweep |
| Security updates | `unattended-upgrades`, daily | nobody; the monthly checklist looks for a pending reboot |

The thresholds of the Grafana rules are in
`deploy/monitoring/grafana/provisioning/alerting/rules.yaml`; the watch's are in
`app/Client/config/ops.php` under `security`. Change a number in one place and in this
table together.

## 2. How an alert travels

- **Grafana** mails `ALERT_EMAIL` (`deploy/monitoring/.env`); a critical rule repeats every
  four hours while it stands, the rest once a day. Grafana never talks to Central.
- **Central** (`central:health --alert` hourly, `central:security-watch --alert` every ten
  minutes) mails every active administrator plus `OPS_ALERT_EMAIL`, and sends the same
  text by WhatsApp to `WHATSAPP_ALERT_TO` once `WHATSAPP_PROVIDER` names a provider. Until
  then the WhatsApp copy goes to the debug log and nowhere else. Health alerts are one per
  incident, re-armed on recovery; security findings are one per six hours for the same
  address, account or document.
- **CrowdSec** does not alert; it bans, for four hours, at the firewall, every port. The
  ban spike rule in Grafana is how you hear of a wave.

## 3. Applying the kit

In this order, each step once, every step safe to repeat:

1. `bash deploy/provision.sh <domain>` as root (DEPLOY.md, section 1), then a session as
   `central` with your key proven to work.
2. **With that session kept open**, `sudo bash deploy/harden.sh <domain> <monitor-user>`:
   sshd keys-only with root locked out (the image's `50-cloud-init.conf` goes to
   `/root/sshd-backup/`), `unattended-upgrades`, auditd, CrowdSec with its firewall
   bouncer and Central's scenario, Docker bound to loopback, the monitor site in Caddy
   (it asks for the monitor password). `ADMIN_IP=<your address> sudo -E bash …` keeps your
   own address off CrowdSec's list. Then, from a **new** terminal, `ssh central@<domain>`
   must work and `ssh root@<domain>` must be refused; only then close the old session.
3. Hostinger panel, Firewall: 22 from your address, 80 and 443 from anywhere, nothing
   else. ufw on the box says the same; the panel is the second lock.
4. `cp deploy/monitoring/.env.example deploy/monitoring/.env`, fill it in, configure the
   rclone remote as root (`rclone config`; a bucket key that can write but not delete),
   then `sudo bash deploy/monitoring/install.sh`. It prints five checks; do them.
5. The external uptime check, by hand.
6. `WHATSAPP_*` in `/srv/central/.env` when a provider is chosen; `php artisan
   config:cache`.

## 4. Each month

- `sudo needrestart -b` or `ls /var/run/reboot-required*`: a pending kernel reboot is done
  in a quiet hour, after `php artisan down`.
- `sudo cscli hub update && sudo cscli hub upgrade`, then `sudo cscli decisions list` and
  `sudo cscli alerts list --since 720h`: what was banned, from where, for what.
- The image tags in `deploy/monitoring/docker-compose.yml` against their releases; bump,
  commit, deploy, `sudo bash deploy/monitoring/install.sh`.
- Grafana → Alerting → Contact points → `owner-mail` → Test: the mail still arrives.
- The last ten staff sign-ins on the Operations screen: every address is one you know.
- `sudo auditctl -l` lists the rules; `sudo ausearch -k central-env --start this-month`
  shows who read `.env` (deploys do; nothing else should).
- `rclone ls <remote>:logs/` shows one archive per night.

## 5. Each quarter

- The restore drill (DEPLOY.md, section 6), attested on Launch Readiness.
- Rotate `MONITOR_DB_PASSWORD` (edit `.env`, run `install.sh`) and Grafana's admin
  password.
- `/etc/central/listening-allowlist` against `ss -tlnup`: a line for every socket you mean
  to have, nothing else.
- `/home/central/.ssh/authorized_keys` and `/etc/sudoers.d/`: every key and every line is
  a person who still works here.
- The access groups and the administrators in Central (Settings → Users, Access Groups).
- `sudo ausearch -k sudoers --start this-quarter`, `-k identity`, `-k sshd`: each change is
  one you made.

## 6. The day something is wrong

Signs: an alert you did not cause (an SSH login, `sudo`, an access file, a new port, an
outbound spike), a receipt or price that moved without a person's name on it, a buyer or
staff member saying they did not do what the log says they did.

**Isolate first, investigate second.** Nothing below destroys evidence.

1. Hostinger panel, Firewall: deny everything except SSH from your own address. On the
   box, `sudo ufw deny 80 && sudo ufw deny 443` as well.
2. `cd /srv/central && php artisan down`, `sudo supervisorctl stop all`: no new postings
   while you look.
3. Ship the evidence now, not tonight: `sudo central-ship-logs` for yesterday and
   `sudo central-ship-logs $(date +%F)` for today. Copy `/var/log/audit/audit.log` and
   `storage/logs/security-*.log` somewhere off the box too.
4. Read: `sudo ausearch -k central-env -k sshd -k sudoers -k identity --start today`,
   `journalctl -u ssh --since today`, `sudo cscli alerts list --since 24h`, the Activity
   Log filtered by the hour, Grafana's "SSH, sudo and CrowdSec" panel.
5. If the box was entered (a login you did not make, a changed file, a process you do not
   know): do not clean it, **rebuild it** (section 7). A cleaned box is a box you hope is
   clean.
6. If only the application was misused (a stolen staff password, a buyer's login): reset
   that password, end its sessions (deactivate and reactivate the user), read every
   Activity Log row in its name since the last login you trust, and reverse what it did
   through the documents, never through the ledgers.
7. Rotate what the intruder could have read: the database password, `APP_KEY` only with
   the old one in `APP_PREVIOUS_KEYS`, the bucket keys, the deploy key, the SMTP password,
   `BACKUP_ENCRYPTION_KEY` only after a fresh backup with the new one is verified.
8. Write down what happened, when, and what was done, in `docs/` or the company's own
   records. Personal data touched means the UU PDP notice duties apply
   ([PRIVACY.md](PRIVACY.md)).

## 7. Rebuilding from a backup

A fresh VPS, the same steps as the first time, in under an hour:

```bash
bash deploy/provision.sh <domain>                      # as root
sudo bash deploy/harden.sh <domain> <monitor-user>     # as central, old session open
cd /srv/central && bash deploy/deploy.sh --first       # then .env with NEW secrets and the OLD keys
php artisan central:restore --force                    # the newest verified backup
php artisan optimize && php artisan up
sudo bash deploy/monitoring/install.sh
```

The old `APP_KEY` and `BACKUP_ENCRYPTION_KEY` are needed to read the backup; everything
else (passwords, bucket keys, deploy key) is new. Point the domain at the new address last.
Then the quarterly list, in full, once.
