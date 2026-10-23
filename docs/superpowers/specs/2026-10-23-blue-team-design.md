# Blue team — design

Sub-project 7 of `docs/ROADMAP.md`. Status: approved 2026-10-23.

## Goal

Central runs on one Ubuntu 24.04 VPS at Hostinger. Sub-project 6 gave it a deploy kit,
encrypted off-box backups, seven health checks with an hourly critical mail, a nightly
integrity sweep and the Operations screen. What it does not have is a defence: SSH
hardening is a manual note, nothing patches the box, nothing blocks an attacker who guesses
passwords, no log leaves the machine, no metric is kept, nobody is told when someone logs
in over SSH or when an administrator signs in from a new address, and a failed login into
the panel is not even recorded. Alerts reach email only.

Decisions with the user: the monitoring stack is **self-hosted on the same VPS** (no third
party); alerts go by **email and WhatsApp**, the WhatsApp provider chosen later behind an
interface; **Central-level security alerts are included** (failed logins, rights changes,
the segregation-of-duties switch, the price brake, a receipt edited after posting).

Two limits are stated rather than hidden. The logs live on the box being attacked, so a
nightly archive goes to the backup bucket with a key that can write but not delete. And a
box cannot report that it is gone, so one free external HTTPS check (not code) watches
`/up` and the certificate from outside.

## Decisions

| Question | Decision | Why |
|---|---|---|
| Extend `provision.sh`? | A new `deploy/harden.sh`, run as root, idempotent, in the same style. `provision.sh` only points to it. | Hardening re-runs on its own schedule and needs a second open SSH session; `DeployKitTest` pins the first-hour script. |
| sshd settings | A drop-in `deploy/sshd/00-central.conf`. `harden.sh` moves the image's `50-cloud-init.conf` aside, runs `sshd -t` and refuses to start unless `/home/central/.ssh/authorized_keys` holds a key. | sshd takes the first value of a key, and cloud images ship `PasswordAuthentication yes` in `50-cloud-init.conf`; a `99-` file would lose. |
| Rate limit on the login pages in Caddy | None. Filament's own limit (5 attempts a minute per address) stays; CrowdSec gets a scenario fed by a new `security` JSON log that Central writes on every failed login. | Filament's login posts to `/livewire/update`, so a Caddy path matcher never sees the attempt; an xcaddy build with the rate-limit plugin would leave apt and stop receiving security updates. |
| Where Grafana is reached | `monitor.<domain>`: a second site block in the Caddyfile, Caddy `basic_auth` in front of Grafana's own login, Grafana bound to `127.0.0.1:3000`. ufw unchanged. | A subdomain leaves Central's cookies, content policy and `SecurityHeaders` untouched; `basic_auth` is not the `header` directive the kit test forbids. |
| Containers and the firewall | Every container uses `network_mode: host` and listens on `127.0.0.1:<port>`; nothing is published with `ports:`; `/etc/docker/daemon.json` sets `"ip": "127.0.0.1"`. | Docker's published ports bypass ufw; the exporters must reach PostgreSQL and Redis, which listen on loopback only. |
| Queue depth, failed jobs, backup state | No Central code: Grafana reads PostgreSQL through a read-only `monitor` role, and `redis_exporter` reports the size of the queue key. | These rules must still fire when Central's scheduler, Redis or mail is the thing that died. |
| Alert rules | Grafana-managed rules, provisioned from files, over Prometheus, Loki and PostgreSQL. No Alertmanager. | One place to read, one contact point. |
| New listening port, certificate expiry | node_exporter's textfile collector, fed by a root cron script every five minutes against `/etc/central/listening-allowlist`. | No blackbox exporter, no extra container; the allowlist is the written baseline of the box. |
| Grafana and WhatsApp | Grafana sends email only. Central sends email and WhatsApp. No webhook into Central. | An inbound endpoint is new attack surface on the one box being protected. When a provider is chosen, Grafana's webhook contact point can post to the provider directly. |
| The actor on a login audit row | One named base edit: `App\Domain\Audit\Auditor::log()` gains an optional trailing `?int $userId = null`. | Laravel fires `Login` before `setUser()`, so `auth()->id()` is null inside the listener, and `audit_logs` is append-only. |
| Where the security view lives | A fourth card on the Operations page. No new screen key. | Same audience, same "how the box stands" page; no new rights row, route test or notes section. |

## 1. Host hardening (`deploy/`)

- `deploy/harden.sh`, root, idempotent, `set -euo pipefail`, the `say()` helper of
  `provision.sh`. In order: a pre-flight that refuses unless root and unless
  `/home/central/.ssh/authorized_keys` has a key, and tells the operator to keep the current
  session open; the sshd drop-in installed, `50-cloud-init.conf` moved to
  `/root/sshd-backup/`, `sshd -t`, `systemctl restart ssh`; `unattended-upgrades` and
  `needrestart` with `deploy/apt/20auto-upgrades` and `deploy/apt/50unattended-upgrades`
  (security origin only, no automatic reboot, unused dependencies removed); `auditd` with
  `deploy/auditd/central.rules` loaded by `augenrules --load`; CrowdSec from its apt
  repository added by hand with a `signed-by` keyring (no installer piped to a shell, as
  the kit test demands), `crowdsec` and `crowdsec-firewall-bouncer-iptables` (the
  iptables bouncer coexists with ufw), the collections `crowdsecurity/sshd`,
  `crowdsecurity/caddy`, `crowdsecurity/linux`, `crowdsecurity/http-cve`, the kit's
  acquisition, parser and scenario files copied in, an optional whitelist from `ADMIN_IP`;
  Docker, `docker-compose-v2` and `rclone` installed, `/etc/docker/daemon.json` written;
  Caddy reloaded; and a printed list of what stays manual (mirror the firewall in the
  Hostinger panel, test SSH from a second terminal, `cscli decisions list`).
- `deploy/sshd/00-central.conf`: `PermitRootLogin no`, `PasswordAuthentication no`,
  `KbdInteractiveAuthentication no`, `PubkeyAuthentication yes`, `PermitEmptyPasswords no`,
  `MaxAuthTries 3`, `LoginGraceTime 30`, `AllowUsers central`, `X11Forwarding no`,
  `AllowAgentForwarding no`, `AllowTcpForwarding no`, `ClientAliveInterval 300`,
  `ClientAliveCountMax 2`. The GitHub deploy runs one command over SSH and needs no
  forwarding.
- `deploy/auditd/central.rules`: watches on `/srv/central/.env` (read, write, attribute),
  `/etc/caddy`, `/etc/ssh`, `/etc/sudoers` and `/etc/sudoers.d`, the identity files
  (`passwd`, `group`, `shadow`, `gshadow`), `/etc/crowdsec`, `/etc/docker` and
  `/srv/central/deploy`, each with a key; `-e 1`, not `-e 2`, so a rule change does not
  need a reboot.
- `deploy/crowdsec/acquis.d/central.yaml`: the journal of `ssh.service` (Ubuntu 24.04
  minimal has no `/var/log/auth.log`), the Caddy JSON log, and
  `/srv/central/storage/logs/security-*.log` labelled `central-security`.
  `deploy/crowdsec/parsers/s01-parse/central-security-log.yaml` reads the JSON line
  (`event`, `ip`); `deploy/crowdsec/scenarios/central-login-bf.yaml` is a leaky bucket of
  ten failed logins in ten minutes from one address, banned four hours at the firewall.
  This replaces the Caddy rate limit.
- `deploy/Caddyfile`: the existing log gains `mode 0640` so the shipper can read it; a
  second block `monitor.__DOMAIN__` with `basic_auth` (`__MONITOR_USER__`,
  `__MONITOR_HASH__` from `caddy hash-password`), `reverse_proxy 127.0.0.1:3000` and its
  own log. `harden.sh` substitutes the placeholders; `provision.sh` leaves the block out
  when they are unset, so a plain provision still works. No `header` directive.
- `deploy/provision.sh`: one more line in its closing list, pointing to `harden.sh`.
- `.github/workflows/laravel.yml`, lint job: `bash -n` over the new scripts, `shellcheck`
  over `deploy/*.sh`, `deploy/monitoring/*.sh` and the textfile script, and
  `docker compose config -q` over the monitoring stack.

## 2. Monitoring stack (`deploy/monitoring/`)

- `docker-compose.yml`: `grafana`, `loki`, `prometheus`, `alloy`, `node_exporter`,
  `postgres_exporter`, `redis_exporter`; every service on the host network with pinned
  image tags, named volumes for the three stores, `restart: unless-stopped`, `env_file:
  .env`. Alloy runs as root with read-only mounts of `/var/log/caddy`,
  `/srv/central/storage/logs`, `/var/log/journal`, `/etc/machine-id` and `/var/log/audit`.
- `.env.example` is committed and `.env` is not (the root `.gitignore` already ignores
  `.env` at any depth): the Grafana admin password, `GF_SMTP_*` (the same SMTP as Central),
  `ALERT_EMAIL`, `MONITOR_DB_PASSWORD`, `REDIS_QUEUE_KEY`, `DOMAIN`, `LOGS_RCLONE_REMOTE`.
- `prometheus/prometheus.yml` scrapes the exporters, Loki, Alloy and itself, 30 days kept.
  `loki/loki.yml` is the single-binary filesystem setup with 30 days retention.
  `alloy/config.alloy` reads the journal (ssh, crowdsec, the bouncer, supervisor, docker,
  cron), the Caddy JSON log (`job="caddy"`), `laravel-*.log` with a multiline stage
  (`job="laravel"`), `security-*.log` (`job="central-security"`) and `audit.log`
  (`job="auditd"`), and writes to Loki on loopback.
- `grafana/provisioning/`: the three datasources (PostgreSQL through the `monitor` role),
  the email contact point, the notification policy, the alert rules and one "Central box"
  dashboard.
- The alert rules: an SSH login accepted; sudo used; more than five CrowdSec bans in
  fifteen minutes; more than twenty 5xx responses in five minutes; the disk above 80 %;
  the queue above 200 jobs for ten minutes; any failed job; a backup that failed in the
  last day or no verified offsite backup for 26 hours; CPU above 90 % for thirty minutes
  (a miner); outbound traffic above 20 MB/s for ten minutes (exfiltration); a listening
  port outside the allowlist; the certificate expiring within fourteen days; an auditd
  event on the identity, sudoers, sshd or `.env` keys; more than twenty failed panel logins
  in fifteen minutes.
- `textfile/collect.sh` (root cron, five minutes) writes
  `central_unexpected_listening_port{proto,addr,port,process}` from `ss -Hltnu` against the
  allowlist and `central_tls_cert_expiry_seconds` from `openssl s_client`, atomically.
  `ship-logs.sh` (root cron, 03:00) archives yesterday's Caddy, security and audit logs,
  the ssh and crowdsec journal and `cscli alerts list -o json`, and copies the archive with
  `rclone` under `logs/YYYY/MM/DD/` using a bucket key that can write but not delete.
  `install.sh` (root) creates the `monitor` role, the textfile directory and the two cron
  lines, then `docker compose up -d`. A short `README.md` says how to apply, upgrade and
  verify.

## 3. Alert channels (`app/Client/Domain/Ops/Alerts/`)

- `Alert`, a final value object: severity (the existing `OpsStatus`), title, lines, a
  throttle key, and `mailable()`. `Alert::health(list<OpsCheck>)` wraps the existing
  `SystemAlertMessage`, so `OpsHealthTest` keeps passing; `Alert::security(list<SecurityFinding>)`
  gives the new `App\Client\Mail\SecurityAlertMessage` with its text view.
- `AlertChannel` (`send(Alert): void`, `name(): string`). `MailChannel` reuses
  `OpsAlerter::recipients()` and `Locales::using(Locales::companyDefault(), …)`.
  `WhatsAppSender` (`send(string $to, string $text): void`) with a `NullSender` that logs at
  debug and never throws; the Fonnte or Twilio sender comes later behind the same
  interface. `WhatsAppChannel` renders the alert as plain text and sends it to each number
  in `ops.whatsapp.recipients`.
- `AlertDispatcher::dispatch(Alert): list<string>` returns the channels that succeeded: mail
  always, WhatsApp only when `ops.whatsapp.provider` is set and recipients exist; each
  channel in its own try/catch with `Log::error`, so one failing channel never silences the
  other. `OpsAlerter::sweep()` keeps its throttle and recovery and ends with the
  dispatcher.
- `app/Client/config/ops.php` gains `whatsapp` (`WHATSAPP_PROVIDER`, `WHATSAPP_TOKEN`,
  `WHATSAPP_ALERT_TO` as comma-separated E.164 numbers) and `security` (the thresholds of
  section 4 and `throttle_hours`). `ClientServiceProvider` binds `WhatsAppSender` by
  provider (default `NullSender`) and defines the `security` log channel in code (daily,
  `storage/logs/security.log`, 30 days, Monolog's `JsonFormatter`), so `config/logging.php`
  is not edited. `.env.example` gains the three keys.

## 4. Security watch (`app/Client/Domain/Ops/Security/`)

- `RecordsAuthEvents`, an event subscriber registered in `ClientServiceProvider::boot()`:
  `Illuminate\Auth\Events\Failed` → `Auditor::log('login_failed', …)` with guard, email and
  address; `Login` → `login_succeeded` with the user id passed explicitly, for the `web`
  and `customer` guards; `Lockout` → `login_locked_out`; `PasswordReset` on `web` →
  `password_reset` (the portal already logs its own). Each handler also writes one JSON line
  to `Log::channel('security')`: `event`, `guard`, `email`, `ip`, `user_id`. The address is
  right only with `TRUSTED_PROXIES=127.0.0.1`, which DEPLOY.md already requires.
- The base edit named above: `Auditor::log(string $action, $subject, $reference = null,
  array $meta = [], ?int $userId = null)` with `'user_id' => $userId ?? auth()->id()`.
- `app/Client/database/migrations/2026_10_23_000100_add_action_index_to_audit_logs.php`:
  an index on `(action, created_at)`; the watch and the page query by action over a window
  on a table that only grows.
- `SecurityFinding` (rule key, severity, title, detail, subject for the throttle) and
  `SecurityWatch::findings(CarbonInterface $since)`, rules as private methods like
  `OpsHealth`, thresholds from `ops.security`:
  - `failed_logins_ip`: ten or more `login_failed` rows from one address in the window;
    `failed_logins_account`: five or more for one email.
  - `admin_new_ip`: an administrator's `login_succeeded` from an address with no earlier
    `login_succeeded` for that user in ninety days, and at least one earlier login (a first
    login ever is not an alert).
  - `access_changed`: `rights_changed`, `special_rights_changed`, `memberships_changed`, or
    an `updated` on a user whose access type became administrator.
  - `sod_off`: `preference_changed` with key `rules.segregation_of_duties` and a falsy new
    value.
  - `price_brake_published`: `price_list_published` with a non-empty `meta.brake`.
  - `receipt_amount_edited`: an `updated` on a sales receipt whose amount, date or customer
    changed. Allocations are regenerated on edit, so the change itself is the signal.
  - `document_deleted_off_hours`: a receipt or invoice deleted outside business hours.
- A per-rule, per-subject throttle (`Cache::add("ops:security:{rule}:{sha1(subject)}")`,
  `ops.security.throttle_hours`, default 6) that fails open when the cache is down, like
  `OpsAlerter`.
- `central:security-watch {--alert} {--since=20}` (minutes) prints the findings and exits
  0 or 1; with `--alert` it dispatches `Alert::security(…)` for the findings not throttled.
  `OpsModule` registers it and schedules it every ten minutes,
  `withoutOverlapping()->onOneServer()`; the twenty-minute window overlaps and the throttle
  absorbs it.
- The Operations page gains a Security card: the last day's failed logins by guard, the
  last ten administrator sign-ins with address and time, the findings since yesterday, and
  whether the watch is throttled. Every string through `__()`; the Indonesian added to
  `lang/id.json`.

## 5. Documents

- `docs/BLUE-TEAM.md`, the runbook: what is watched (signal, where it is seen, who is
  told); how alerts travel; the apply procedure; the monthly checklist (pending reboot,
  `cscli hub update && cscli hub upgrade`, image tags, a Grafana test alert, the
  administrators' addresses, `cscli decisions list`); the quarterly checklist (the restore
  drill, rotating `MONITOR_DB_PASSWORD`, the allowlist, sudoers and authorized keys, the
  audit log); incident isolation (deny everything but your own address in the Hostinger
  panel firewall, `ufw deny 80` and `443`, `php artisan down`, `supervisorctl stop all`,
  keep the logs, run `ship-logs.sh` by hand); rebuild (a fresh VPS, provision, harden,
  `central:restore --force`, rotate every secret); and the two limits.
- `docs/DEPLOY.md`: section 1 adds `harden.sh`; a new section on hardening and monitoring
  pointing to the runbook; the `.env` table gains `WHATSAPP_*`; the schedule gains the
  watch; the troubleshooting section gains "locked out by CrowdSec" and "Grafana
  unreachable".
- `docs/ROADMAP.md` row 7; `docs/standard/_notes/settings.md` for the Operations card;
  `php artisan erp:standard`.

## 6. Tests (`tests/Feature/Client/Ops/`)

- `SecurityEventsTest`: a wrong password on the admin login writes `login_failed` with
  address, email, guard and no user; a right one writes `login_succeeded` with the user;
  the same on the portal guard; the security channel receives a JSON line.
- `SecurityWatchTest`: seeded audit rows for each rule produce the expected findings;
  `--alert` sends once, a second run sends nothing, a different subject sends again;
  `sod_off` fires only on a falsy value; `admin_new_ip` ignores a first login.
- `AlertChannelsTest`: provider unset gives mail only and `NullSender`; a fake sender bound
  gives both; a sender that throws does not stop the mail; `OpsHealthTest` stays green.
- `OpsScreensTest`: the Operations page shows the Security card and an administrator's
  sign-in.
- `DeployKitTest`: the new scripts parse; the sshd drop-in holds the three key lines; every
  compose service is on the host network and none publishes a port; the Caddyfile has the
  monitor block, the reverse proxy to loopback, and still no `header`; the lint job runs
  `shellcheck` and `docker compose config`.

## 7. Order and sign-off

1. This spec and the plan. Sign-off 1: the decisions table.
2. Section 1 and the lint step. Sign-off 2, then the operator runs `sudo bash
   deploy/harden.sh` with a second SSH session open.
3. Sections 3, 4 and 6, the strings, the notes, `erp:standard`. Sign-off 3 on a green
   suite; deploy through the `production` branch.
4. Section 2 and the compose check. Sign-off 4, then the operator runs
   `deploy/monitoring/install.sh`, signs in at `monitor.<domain>` and fires a test alert.
5. Section 5 finished as each part lands; the roadmap row last. Sign-off 5.

What the repository cannot verify, the operator does from the runbook: `ssh root@host` is
refused and `ssh central@host` works from a new terminal; `sudo sshd -T` shows the three
settings; `sudo cscli metrics` shows the three acquisitions reading; `sudo auditctl -l`;
`unattended-upgrade --dry-run`; `docker compose ps` all up; a deliberate failed admin login
appears in Grafana under `{job="central-security"}`; the textfile metrics exist; the
contact-point test delivers; `rclone ls` shows the first night's archive. The Hostinger
panel firewall and the external uptime check are manual.
