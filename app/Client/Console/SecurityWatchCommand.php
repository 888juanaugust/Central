<?php

declare(strict_types=1);

namespace App\Client\Console;

use App\Client\Domain\Ops\Alerts\Alert;
use App\Client\Domain\Ops\Alerts\AlertDispatcher;
use App\Client\Domain\Ops\Security\SecurityFinding;
use App\Client\Domain\Ops\Security\SecurityWatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * php artisan central:security-watch            # what the last window holds
 * php artisan central:security-watch --alert    # and tell the administrators what is new
 *
 * Exit 0 when the window is clean, 1 when there is something to look at.
 */
class SecurityWatchCommand extends Command
{
    protected $signature = 'central:security-watch {--alert : Send the findings not reported within the throttle} {--since= : Minutes to look back (default from config)}';

    protected $description = 'Read the Activity Log for signs of an attack or a misuse';

    public function handle(SecurityWatch $watch, AlertDispatcher $dispatcher): int
    {
        $minutes = (int) ($this->option('since') ?: config('ops.security.window_minutes'));
        $findings = $watch->findings(now()->subMinutes($minutes));

        if ($findings === []) {
            $this->info(__('Nothing to report in the last :minutes minutes.', ['minutes' => $minutes]));

            return self::SUCCESS;
        }
        foreach ($findings as $finding) {
            $this->line(sprintf('%-8s %s', strtoupper($finding->severity->label()), $finding->line()));
        }
        if ($this->option('alert')) {
            $fresh = $watch->claim($findings);
            if ($fresh !== []) {
                $delivered = $dispatcher->dispatch(Alert::security($fresh));
                Log::warning('Security alert sent', ['channels' => $delivered, 'findings' => array_map(fn (SecurityFinding $f) => $f->rule.': '.$f->detail, $fresh)]);
                $this->comment(__(':count finding(s) sent through :channels.', ['count' => count($fresh), 'channels' => implode(', ', $delivered) ?: __('no channel')]));
            } else {
                $this->comment(__('Every finding was already reported within the throttle.'));
            }
        }

        return self::FAILURE;
    }
}
