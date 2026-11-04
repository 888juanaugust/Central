<?php

declare(strict_types=1);

namespace App\Client\Domain\Ops\Security;

use App\Domain\Pengaturan\PreferensiKey;
use App\Models\Company\AuditLog;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Reads the Activity Log over a window and names what a person should look
 * at: guessed passwords, an administrator from a new address, rights that
 * moved, the segregation-of-duties switch turned off, a price list published
 * over the brake, a posted receipt whose amount changed, a receipt or
 * invoice deleted at night. Thresholds in config/ops.php (security). The
 * watch reads and reports; it never changes a row.
 */
class SecurityWatch
{
    /** @return list<SecurityFinding> */
    public function findings(CarbonInterface $since): array
    {
        return [
            ...$this->failedLoginsByAddress($since),
            ...$this->failedLoginsByAccount($since),
            ...$this->administratorFromNewAddress($since),
            ...$this->accessChanged($since),
            ...$this->segregationSwitchedOff($since),
            ...$this->priceBrakePublished($since),
            ...$this->receiptAmountEdited($since),
            ...$this->documentDeletedOffHours($since),
        ];
    }

    /**
     * The findings not reported within the throttle, each now claimed. When
     * the cache is down every finding passes: silence is the worse failure.
     *
     * @param  list<SecurityFinding>  $findings
     * @return list<SecurityFinding>
     */
    public function claim(array $findings): array
    {
        $hours = (int) config('ops.security.throttle_hours');
        $fresh = [];
        foreach ($findings as $finding) {
            try {
                if (Cache::add($finding->throttleKey(), time(), now()->addHours($hours))) {
                    $fresh[] = $finding;
                }
            } catch (Throwable) {
                $fresh[] = $finding;
            }
        }

        return $fresh;
    }

    /** @return Builder<AuditLog> */
    private function rows(CarbonInterface $since, string ...$actions): Builder
    {
        return AuditLog::query()->with('user')->whereIn('action', $actions)->where('created_at', '>=', $since)->orderBy('id');
    }

    /** @return list<SecurityFinding> */
    private function failedLoginsByAddress(CarbonInterface $since): array
    {
        $limit = (int) config('ops.security.failed_logins_per_ip');
        $findings = [];
        foreach ($this->rows($since, 'login_failed')->whereNotNull('ip')->get()->groupBy('ip') as $ip => $rows) {
            if ($rows->count() >= $limit) {
                $accounts = $rows->pluck('reference')->filter()->unique()->count();
                $findings[] = SecurityFinding::critical('failed_logins_ip', __('Password guessing from one address'), __(':count failed logins from :ip against :accounts account(s)', ['count' => $rows->count(), 'ip' => $ip, 'accounts' => $accounts]), (string) $ip);
            }
        }

        return $findings;
    }

    /** @return list<SecurityFinding> */
    private function failedLoginsByAccount(CarbonInterface $since): array
    {
        $limit = (int) config('ops.security.failed_logins_per_account');
        $findings = [];
        foreach ($this->rows($since, 'login_failed')->whereNotNull('reference')->get()->groupBy('reference') as $email => $rows) {
            if ($rows->count() >= $limit) {
                $addresses = $rows->pluck('ip')->filter()->unique()->count();
                $findings[] = SecurityFinding::warning('failed_logins_account', __('Password guessing against one account'), __(':count failed logins for :email from :addresses address(es)', ['count' => $rows->count(), 'email' => $email, 'addresses' => $addresses]), (string) $email);
            }
        }

        return $findings;
    }

    /** @return list<SecurityFinding> */
    private function administratorFromNewAddress(CarbonInterface $since): array
    {
        $days = (int) config('ops.security.known_ip_days');
        $findings = [];
        $logins = $this->rows($since, 'login_succeeded')->where('document_type', 'user')->whereNotNull('ip')->whereNotNull('user_id')->get();
        $administrators = User::query()->whereIn('id', $logins->pluck('user_id')->unique())->where('access_type', 'administrator')->get()->keyBy('id');
        foreach ($logins as $login) {
            $user = $administrators->get($login->user_id);
            if ($user === null) {
                continue;
            }
            $earlier = AuditLog::query()->where('action', 'login_succeeded')->where('user_id', $login->user_id)->where('id', '<', $login->id);
            if (! (clone $earlier)->exists()) {
                continue; // a first login ever is not news
            }
            if ((clone $earlier)->where('ip', $login->ip)->where('created_at', '>=', $login->created_at->copy()->subDays($days))->exists()) {
                continue;
            }
            $findings[] = SecurityFinding::warning('admin_new_ip', __('Administrator signed in from a new address'), __(':name from :ip at :time', ['name' => $user->name, 'ip' => $login->ip, 'time' => $login->created_at->format('d/m/Y H:i')]), $user->id.'@'.$login->ip);
        }

        return $findings;
    }

    /** @return list<SecurityFinding> */
    private function accessChanged(CarbonInterface $since): array
    {
        $findings = [];
        foreach ($this->rows($since, 'rights_changed', 'special_rights_changed', 'memberships_changed')->get() as $row) {
            $findings[] = SecurityFinding::warning('access_changed', __('Access rights changed'), __(':action on :reference by :who', ['action' => $row->action, 'reference' => (string) $row->reference, 'who' => $this->actor($row)]), $row->action.'#'.$row->id);
        }
        foreach ($this->rows($since, 'updated')->where('document_type', 'user')->get() as $row) {
            if (data_get($row->meta, 'after.access_type') === 'administrator') {
                $findings[] = SecurityFinding::critical('access_changed', __('A user became an administrator'), __(':reference, by :who', ['reference' => (string) $row->reference, 'who' => $this->actor($row)]), 'administrator#'.$row->id);
            }
        }

        return $findings;
    }

    /** @return list<SecurityFinding> */
    private function segregationSwitchedOff(CarbonInterface $since): array
    {
        $findings = [];
        foreach ($this->rows($since, 'preference_changed')->get() as $row) {
            if (data_get($row->meta, 'key') === PreferensiKey::SegregationOfDuties->value && ! data_get($row->meta, 'new')) {
                $findings[] = SecurityFinding::critical('sod_off', __('Segregation of duties switched off'), __('by :who at :time', ['who' => $this->actor($row), 'time' => $row->created_at->format('d/m/Y H:i')]), 'sod#'.$row->id);
            }
        }

        return $findings;
    }

    /** @return list<SecurityFinding> */
    private function priceBrakePublished(CarbonInterface $since): array
    {
        $findings = [];
        foreach ($this->rows($since, 'price_list_published')->get() as $row) {
            $brake = (array) data_get($row->meta, 'brake', []);
            if ($brake !== []) {
                $findings[] = SecurityFinding::warning('price_brake_published', __('Price list published over the safety brake'), __('version :reference by :who: :reasons', ['reference' => (string) $row->reference, 'who' => $this->actor($row), 'reasons' => implode('; ', array_map('strval', $brake))]), 'brake#'.$row->id);
            }
        }

        return $findings;
    }

    /** @return list<SecurityFinding> */
    private function receiptAmountEdited(CarbonInterface $since): array
    {
        $findings = [];
        foreach ($this->rows($since, 'updated')->where('document_type', 'sales_receipt')->get() as $row) {
            $before = (array) data_get($row->meta, 'before', []);
            $after = (array) data_get($row->meta, 'after', []);
            $moved = [];
            foreach (['amount', 'total', 'trans_date', 'customer_id', 'bank_account_id'] as $column) {
                if (array_key_exists($column, $after) && ($before[$column] ?? null) != $after[$column]) {
                    $moved[] = $column;
                }
            }
            if ($moved !== []) {
                $findings[] = SecurityFinding::critical('receipt_amount_edited', __('A posted receipt was changed'), __(':reference by :who: :columns', ['reference' => (string) $row->reference, 'who' => $this->actor($row), 'columns' => implode(', ', $moved)]), 'receipt#'.$row->id);
            }
        }

        return $findings;
    }

    /** @return list<SecurityFinding> */
    private function documentDeletedOffHours(CarbonInterface $since): array
    {
        [$open, $close] = (array) config('ops.security.business_hours', [7, 19]);
        $findings = [];
        foreach ($this->rows($since, 'deleted')->whereIn('document_type', ['sales_receipt', 'sales_invoice'])->get() as $row) {
            $hour = (int) $row->created_at->copy()->setTimezone((string) config('app.timezone'))->format('G');
            if ($hour < (int) $open || $hour >= (int) $close) {
                $findings[] = SecurityFinding::warning('document_deleted_off_hours', __('A receipt or invoice was deleted outside business hours'), __(':type :reference by :who at :time', ['type' => (string) $row->document_type, 'reference' => (string) $row->reference, 'who' => $this->actor($row), 'time' => $row->created_at->format('d/m/Y H:i')]), 'deleted#'.$row->id);
            }
        }

        return $findings;
    }

    private function actor(AuditLog $row): string
    {
        return $row->user?->name ?? __('nobody signed in');
    }
}
