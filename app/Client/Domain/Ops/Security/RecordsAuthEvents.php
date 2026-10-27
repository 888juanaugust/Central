<?php

declare(strict_types=1);

namespace App\Client\Domain\Ops\Security;

use App\Domain\Audit\Auditor;
use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Every sign-in attempt on either guard goes on the record twice: a row in
 * the Activity Log for the security watch and the Operations screen, and
 * one JSON line in the security log for CrowdSec and the log shipper. A
 * failed attempt names the address and the email tried; a successful one
 * names the user. The guard has not set its user yet when Login fires, so
 * the actor is passed to the Auditor explicitly.
 */
final class RecordsAuthEvents
{
    public const CHANNEL = 'security';

    /** @return array<class-string, string> */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Failed::class => 'failed',
            Login::class => 'login',
            Lockout::class => 'lockout',
            PasswordReset::class => 'passwordReset',
        ];
    }

    public function failed(Failed $event): void
    {
        $email = $this->email($event->credentials);
        $subject = $event->user instanceof Model ? $event->user : null;
        $this->record('login_failed', $subject, $email, ['guard' => $event->guard, 'email' => $email]);
    }

    public function login(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof Model) {
            return;
        }
        $email = (string) $user->getAttribute('email');
        $this->record('login_succeeded', $user, null, ['guard' => $event->guard, 'email' => $email, 'remember' => $event->remember], $user instanceof User ? (int) $user->getKey() : null);
    }

    public function lockout(Lockout $event): void
    {
        $email = $this->email($event->request->only('email', 'data.email'));
        $this->record('login_locked_out', null, $email, ['guard' => null, 'email' => $email]);
    }

    public function passwordReset(PasswordReset $event): void
    {
        // The portal logs its own (portal_password_reset); this is the staff panel's.
        if (! $event->user instanceof User) {
            return;
        }
        $this->record('password_reset', $event->user, null, ['guard' => 'web', 'email' => (string) $event->user->email], (int) $event->user->getKey());
    }

    /** @param  array<string, mixed>  $meta */
    private function record(string $action, ?Model $subject, ?string $reference, array $meta, ?int $userId = null): void
    {
        // A sign-in always comes over HTTP; the request's address goes into meta too, so the row
        // keeps it where the Auditor (which trusts no request under the console) would leave it empty.
        $ip = request()->ip();
        $meta['ip'] = $ip;
        Auditor::log($action, $subject, $reference, $meta, null, $userId);
        Log::channel(self::CHANNEL)->info($action, ['event' => $action, 'guard' => $meta['guard'] ?? null, 'email' => $meta['email'] ?? null, 'ip' => $ip, 'user_id' => $userId]);
    }

    /** @param  array<string, mixed>  $credentials */
    private function email(array $credentials): string
    {
        $email = $credentials['email'] ?? data_get($credentials, 'data.email') ?? '';

        return mb_substr(mb_strtolower(trim((string) $email)), 0, 120);
    }
}
