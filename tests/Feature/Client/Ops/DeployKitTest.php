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
