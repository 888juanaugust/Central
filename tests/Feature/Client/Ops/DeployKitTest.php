<?php

namespace Tests\Feature\Client\Ops;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/** The deploy kit: the scripts parse, the service files name one place and one user, the workflow deploys the production branch after the suite. */
class DeployKitTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = dirname(__DIR__, 4);
    }

    public function test_the_scripts_parse_and_name_the_application_directory_and_user(): void
    {
        foreach (['deploy/provision.sh', 'deploy/deploy.sh', 'deploy/harden.sh', '.claude/hooks/session-start.sh'] as $script) {
            $process = new Process(['bash', '-n', $this->root.'/'.$script]);
            $process->run();
            $this->assertTrue($process->isSuccessful(), $script.': '.$process->getErrorOutput());
            $this->assertTrue(PHP_OS_FAMILY === 'Windows' || is_executable($this->root.'/'.$script) || $script === '.claude/hooks/session-start.sh', "{$script} is executable");
        }
        $provision = (string) file_get_contents($this->root.'/deploy/provision.sh');
        $this->assertStringContainsString('APP_DIR=/srv/central', $provision);
        $this->assertStringContainsString('APP_USER=central', $provision);
        $this->assertStringContainsString('maxmemory-policy noeviction', $provision);
        $this->assertStringContainsString('schedule:run', $provision);
        $this->assertStringNotContainsString('| bash', $provision, 'no installer piped to a shell');
        $this->assertStringNotContainsString('| php', $provision, 'no installer piped to php');
        $this->assertStringContainsString('installer.sig', $provision);
        $this->assertStringContainsString('signed-by=/etc/apt/keyrings/nodesource.gpg', $provision);

        $deploy = (string) file_get_contents($this->root.'/deploy/deploy.sh');
        foreach (['git pull --ff-only', 'migrate --force', 'storage:link', 'queue:restart', 'central:launch-check', 'rm -f bootstrap/cache/config.php', 'manifest.json'] as $needle) {
            $this->assertStringContainsString($needle, $deploy);
        }
        $this->assertStringContainsString('php artisan down', $deploy);
        $this->assertStringContainsString('php artisan up', $deploy);
    }

    public function test_the_service_files_agree_on_the_paths(): void
    {
        $caddy = (string) file_get_contents($this->root.'/deploy/Caddyfile');
        $this->assertStringContainsString('__DOMAIN__ {', $caddy);
        $this->assertStringContainsString('root * /srv/central/public', $caddy);
        $this->assertStringNotContainsString('header ', $caddy, 'the application sends its own headers');
        $this->assertStringContainsString('monitor.__DOMAIN__ {', $caddy);
        $this->assertStringContainsString('reverse_proxy 127.0.0.1:3000', $caddy, 'Grafana is reached through Caddy only');
        $this->assertStringContainsString('basic_auth', $caddy);
        $this->assertMatchesRegularExpression('/^# monitor-begin$.*^# monitor-end$/ms', $caddy, 'provision.sh strips the block by its markers');
        $this->assertStringContainsString("'/^# monitor-begin$/,/^# monitor-end$/d'", (string) file_get_contents($this->root.'/deploy/provision.sh'));

        $worker = (string) file_get_contents($this->root.'/deploy/supervisor/central-worker.conf');
        $this->assertStringContainsString('[program:central-worker]', $worker);
        $this->assertStringContainsString('/srv/central/artisan queue:work redis', $worker);
        $this->assertStringContainsString('user=central', $worker);
        $this->assertStringContainsString('numprocs=2', $worker);

        $logrotate = (string) file_get_contents($this->root.'/deploy/logrotate/central');
        $this->assertStringContainsString('/srv/central/storage/logs/worker.log', $logrotate);
        $this->assertStringContainsString('copytruncate', $logrotate);
    }

    public function test_the_hardening_kit_locks_the_door_and_installs_nothing_through_a_pipe(): void
    {
        $sshd = (string) file_get_contents($this->root.'/deploy/sshd/00-central.conf');
        foreach (['PermitRootLogin no', 'PasswordAuthentication no', 'KbdInteractiveAuthentication no', 'AllowUsers central', 'MaxAuthTries 3'] as $needle) {
            $this->assertStringContainsString($needle, $sshd);
        }

        $harden = (string) file_get_contents($this->root.'/deploy/harden.sh');
        $this->assertStringContainsString('authorized_keys', $harden, 'refuses to lock passwords out without a key in place');
        $this->assertStringContainsString('sshd -t', $harden, 'the config is tested before sshd restarts');
        $this->assertStringContainsString('50-cloud-init.conf', $harden, 'the image file that re-enables passwords goes aside');
        $this->assertStringContainsString('signed-by=/etc/apt/keyrings/crowdsec.gpg', $harden);
        $this->assertStringContainsString('crowdsec-firewall-bouncer-iptables', $harden, 'the iptables bouncer, beside ufw');
        $this->assertStringContainsString('"ip": "127.0.0.1"', $harden, 'Docker never publishes a port past ufw');
        $this->assertStringContainsString('caddy validate', $harden);
        $this->assertStringNotContainsString('| bash', $harden, 'no installer piped to a shell');
        $this->assertStringNotContainsString('| sh', $harden, 'no installer piped to a shell');
        foreach (['deploy/apt/20auto-upgrades', 'deploy/apt/50unattended-upgrades', 'deploy/auditd/central.rules', 'deploy/crowdsec/acquis.d/central.yaml', 'deploy/crowdsec/parsers/s01-parse/central-security-log.yaml', 'deploy/crowdsec/scenarios/central-login-bf.yaml'] as $file) {
            $this->assertFileExists($this->root.'/'.$file);
            $this->assertStringContainsString(basename($file), $harden, "harden.sh installs {$file}");
        }

        $rules = (string) file_get_contents($this->root.'/deploy/auditd/central.rules');
        foreach (['/srv/central/.env', '/etc/ssh', '/etc/sudoers', '/etc/shadow', '-e 1'] as $needle) {
            $this->assertStringContainsString($needle, $rules);
        }
        $this->assertStringNotContainsString('
-e 2', $rules, 'locked rules need a reboot to change');

        $upgrades = (string) file_get_contents($this->root.'/deploy/apt/50unattended-upgrades');
        $this->assertStringContainsString('-security', $upgrades);
        $this->assertStringContainsString('Automatic-Reboot "false"', $upgrades);

        $scenario = (string) file_get_contents($this->root.'/deploy/crowdsec/scenarios/central-login-bf.yaml');
        $this->assertStringContainsString("evt.Meta.log_type == 'central_login_failed'", $scenario);
        $parser = (string) file_get_contents($this->root.'/deploy/crowdsec/parsers/s01-parse/central-security-log.yaml');
        $this->assertStringContainsString('central_login_failed', $parser);
        $this->assertStringContainsString("evt.Parsed.program == 'central-security'", $parser);
        $acquis = (string) file_get_contents($this->root.'/deploy/crowdsec/acquis.d/central.yaml');
        $this->assertStringContainsString('type: central-security', $acquis);
        $this->assertStringContainsString('/srv/central/storage/logs/security-*.log', $acquis);
    }

    public function test_the_monitoring_stack_stays_on_loopback_and_root_runs_only_its_own_copies(): void
    {
        $dir = $this->root.'/deploy/monitoring';
        foreach (['install.sh', 'ship-logs.sh', 'textfile/collect.sh'] as $script) {
            $process = new Process(['bash', '-n', "{$dir}/{$script}"]);
            $process->run();
            $this->assertTrue($process->isSuccessful(), $script.': '.$process->getErrorOutput());
        }

        $compose = (string) file_get_contents("{$dir}/docker-compose.yml");
        $this->assertStringNotContainsString("\n    ports:", $compose, 'a published port bypasses ufw');
        $this->assertStringContainsString('network_mode: host', $compose);
        foreach (['grafana:', 'loki:', 'prometheus:', 'alloy:', 'node_exporter:', 'postgres_exporter:', 'redis_exporter:'] as $service) {
            $this->assertStringContainsString("\n  {$service}", $compose);
        }
        foreach (['GF_SERVER_HTTP_ADDR: 127.0.0.1', '--web.listen-address=127.0.0.1:9090', '--server.http.listen-addr=127.0.0.1:12345', '--web.listen-address=127.0.0.1:9100', '--web.listen-address=127.0.0.1:9187', '--web.listen-address=127.0.0.1:9121'] as $needle) {
            $this->assertStringContainsString($needle, $compose, 'every service listens on loopback');
        }
        $this->assertStringContainsString('GF_ANALYTICS_REPORTING_ENABLED: "false"', $compose, 'nothing phones home');
        $this->assertStringContainsString('--disable-reporting', $compose);
        $this->assertStringContainsString('reporting_enabled: false', (string) file_get_contents("{$dir}/loki/loki.yml"));
        preg_match_all('/image: (\S+)/', $compose, $images);
        $this->assertCount(7, $images[1]);
        foreach ($images[1] as $image) {
            $this->assertMatchesRegularExpression('/:v?\d+\.\d+\.\d+$/', $image, "{$image} is pinned to a version");
        }

        $env = (string) file_get_contents("{$dir}/.env.example");
        foreach (['DOMAIN=', 'GF_SECURITY_ADMIN_PASSWORD=', 'GF_SMTP_HOST=', 'ALERT_EMAIL=', 'MONITOR_DB_PASSWORD=', 'REDIS_QUEUE_KEY=', 'LOGS_RCLONE_REMOTE='] as $key) {
            $this->assertStringContainsString($key, $env);
        }
        $this->assertFileDoesNotExist("{$dir}/.env", 'the real values are never committed');

        $install = (string) file_get_contents("{$dir}/install.sh");
        $this->assertStringContainsString('/usr/local/sbin/central-collect', $install, "root's cron never runs a file the central user can edit");
        $this->assertStringContainsString('/usr/local/sbin/central-ship-logs', $install);
        $this->assertStringContainsString('GRANT SELECT ON failed_jobs, backup_runs TO monitor', $install);
        $this->assertStringContainsString('change-me', $install, 'the example passwords are refused');

        $alloy = (string) file_get_contents("{$dir}/alloy/config.alloy");
        foreach (['job = "caddy"', 'job = "central-security"', 'job = "auditd"', 'job = "laravel"', 'job = "journal"'] as $label) {
            $this->assertStringContainsString($label, $alloy);
        }
        $rules = (string) file_get_contents("{$dir}/grafana/provisioning/alerting/rules.yaml");
        $this->assertSame(14, preg_match_all('/^      - uid: central-/m', $rules), 'the fourteen rules of the runbook');
        $this->assertStringContainsString('central_unexpected_listening_port', (string) file_get_contents("{$dir}/textfile/collect.sh"));
        $this->assertStringContainsString('central_tls_cert_expiry_seconds', (string) file_get_contents("{$dir}/textfile/collect.sh"));

        $tests = (string) file_get_contents($this->root.'/.github/workflows/laravel.yml');
        $this->assertStringContainsString('docker compose -f deploy/monitoring/docker-compose.yml', $tests);
        $this->assertStringContainsString('deploy/monitoring/*.sh', $tests);
    }

    public function test_the_deploy_workflow_runs_the_suite_first_and_only_from_the_production_branch(): void
    {
        $deploy = (string) file_get_contents($this->root.'/.github/workflows/deploy.yml');
        foreach ([
            "  push:\n    branches: [production]", 'workflow_dispatch:', 'uses: ./.github/workflows/laravel.yml', 'needs: tests', 'environment: production',
            'cancel-in-progress: false', 'StrictHostKeyChecking=yes', 'cd /srv/central && git pull --ff-only && bash deploy/deploy.sh', 'DEPLOY_KNOWN_HOSTS is empty',
        ] as $needle) {
            $this->assertStringContainsString($needle, $deploy);
        }
        $this->assertStringNotContainsString('pull_request', $deploy, 'a pull request never deploys');

        $tests = (string) file_get_contents($this->root.'/.github/workflows/laravel.yml');
        $this->assertStringContainsString('workflow_call:', $tests);
        $this->assertStringContainsString('deploy/deploy.sh', $tests);
        $this->assertStringContainsString('deploy/harden.sh', $tests);
        $this->assertStringContainsString('shellcheck', $tests);
    }
}
