<?php

declare(strict_types=1);

namespace App\Client\Filament\Pages;

use App\Client\Domain\Ops\Backup\BackupHealth;
use App\Client\Domain\Ops\Health\OpsAlerter;
use App\Client\Domain\Ops\Health\OpsCheck;
use App\Client\Domain\Ops\Health\OpsHealth;
use App\Client\Domain\Ops\Integrity\IntegrityFinding;
use App\Client\Domain\Ops\Integrity\LedgerIntegrity;
use App\Client\Domain\Ops\Security\SecurityFinding;
use App\Client\Domain\Ops\Security\SecurityWatch;
use App\Client\Jobs\RunBackup;
use App\Client\Models\BackupRun;
use App\Client\Screens\CentralScreen;
use App\Filament\Support\ErpPage;
use App\Models\Company\AuditLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Operations: how the box stands right now. The seven health checks, the
 * backups (state and the last runs), the ledger integrity findings, the
 * last day of the security watch and whether an alert is throttled. A
 * backup can be asked for from here; it runs on the worker. Administrator
 * only.
 */
class Operations extends ErpPage
{
    protected string $view = 'client.pages.operations';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    public static function menuKey(): CentralScreen
    {
        return CentralScreen::Operations;
    }

    /** @return list<OpsCheck> */
    public function checks(): array
    {
        return app(OpsHealth::class)->checks();
    }

    public function backupHealth(): BackupHealth
    {
        return app(BackupHealth::class);
    }

    /** @return Collection<int, BackupRun> */
    public function runs(): Collection
    {
        return BackupRun::query()->latest('started_at')->limit(10)->get();
    }

    /** @return list<IntegrityFinding> */
    public function findings(): array
    {
        return app(LedgerIntegrity::class)->findings();
    }

    public function alertThrottled(): bool
    {
        try {
            return Cache::has(OpsAlerter::THROTTLE_KEY);
        } catch (\Throwable) {
            return false;
        }
    }

    /** The security watch over the last day, read now. @return list<SecurityFinding> */
    public function securityFindings(): array
    {
        return app(SecurityWatch::class)->findings(now()->subDay());
    }

    /** Failed sign-ins in the last day, per guard. @return array<string, int> */
    public function failedLogins(): array
    {
        return AuditLog::query()->where('action', 'login_failed')->where('created_at', '>=', now()->subDay())
            ->select(DB::raw("coalesce(meta->>'guard', '?') as guard"), DB::raw('count(*) as n'))
            ->groupBy(DB::raw("coalesce(meta->>'guard', '?')"))->pluck('n', 'guard')->map(fn ($n) => (int) $n)->all();
    }

    /** The last staff sign-ins, newest first. @return Collection<int, AuditLog> */
    public function recentLogins(): Collection
    {
        return AuditLog::query()->with('user')->where('action', 'login_succeeded')->where('document_type', 'user')->latest('id')->limit(10)->get();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recheck')->label(__('Check again'))->icon(Heroicon::OutlinedArrowPath)->color('gray')
                ->action(fn () => $this->dispatch('$refresh')),
            Action::make('backup')->label(__('Run a backup now'))->icon(Heroicon::OutlinedArchiveBox)
                ->visible(fn () => static::canUpdate() && (auth()->user()?->isAdministrator() ?? false))
                ->requiresConfirmation()
                ->modalDescription(__('The worker dumps, encrypts, stores and verifies a backup now; its row appears below when done.'))
                ->action(function (): void {
                    RunBackup::dispatch(__('asked for from the Operations screen by :name', ['name' => auth()->user()?->name]));
                    Notification::make()->title(__('Backup queued'))->success()->send();
                }),
        ];
    }
}
