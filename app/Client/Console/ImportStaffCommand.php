<?php

declare(strict_types=1);

namespace App\Client\Console;

use App\Client\Access\CentralGroups;
use App\Domain\Audit\Auditor;
use App\Models\Company\Branch;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Staff logins from a CSV the Owner writes on the server: email, role, name, branches.
 * Each login gets its own temporary password, printed once here and changed at the first
 * sign-in; a mailbox's password is never asked for or reused. A login that already exists
 * is left as it is. Marketing reads every branch; the others get the listed branch codes
 * (separated by |), else the default branch. Administrators are made on the Users screen.
 */
class ImportStaffCommand extends Command
{
    protected $signature = 'central:import-staff
        {file : A CSV with the header email,role,name,branches}
        {--dry-run : Check the file and show what would be made, without writing}';

    protected $description = 'Create staff logins from a CSV, each with a temporary password to change at first sign-in';

    private const ROLES = [CentralGroups::FINANCE, CentralGroups::MARKETING, CentralGroups::SALES, CentralGroups::PURCHASING, CentralGroups::WAREHOUSE];

    public function handle(): int
    {
        $path = (string) $this->argument('file');
        if (! is_readable($path)) {
            $this->error(__('The file :path cannot be read.', ['path' => $path]));

            return self::FAILURE;
        }

        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), (array) fgetcsv($handle));
        $dry = (bool) $this->option('dry-run');
        $made = [];
        $failed = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || trim(implode('', $row)) === '') {
                continue;
            }
            $data = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), ''));
            $email = strtolower(trim((string) ($data['email'] ?? '')));
            $role = strtolower(trim((string) ($data['role'] ?? '')));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->warn(__('Skipped :email: not an email address.', ['email' => $email]));
                $failed++;

                continue;
            }
            if ($role !== '' && ! in_array($role, self::ROLES, true)) {
                $this->warn(__('Skipped :email: the role :role is not one this command gives (:roles).', ['email' => $email, 'role' => $role, 'roles' => implode(', ', self::ROLES)]));
                $failed++;

                continue;
            }
            if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
                $this->line(__(':email already exists; left as it is.', ['email' => $email]));

                continue;
            }

            $codes = array_values(array_filter(array_map('trim', explode('|', (string) ($data['branches'] ?? '')))));
            $branches = $role === CentralGroups::MARKETING
                ? Branch::query()->where('is_active', true)->pluck('id')->all()
                : ($codes === [] ? array_filter([Branch::default()?->id]) : Branch::query()->whereIn('code', $codes)->pluck('id')->all());
            if ($codes !== [] && $role !== CentralGroups::MARKETING && count($branches) !== count($codes)) {
                $this->warn(__('Skipped :email: unknown branch code in :codes.', ['email' => $email, 'codes' => implode('|', $codes)]));
                $failed++;

                continue;
            }

            $name = trim((string) ($data['name'] ?? '')) ?: self::nameFrom($email);
            $password = Str::password(16, symbols: false);

            if (! $dry) {
                DB::transaction(function () use ($email, $name, $password, $role, $branches): void {
                    $user = new User(['name' => $name, 'email' => $email, 'access_type' => 'operator', 'is_active' => true, 'locale' => 'id']);
                    $user->forceFill(['password' => Hash::make($password), 'password_change_required' => true])->save();
                    if ($role !== '') {
                        (CentralGroups::find($role) ?? CentralGroups::claim($role))->users()->attach($user);
                    }
                    $user->branches()->sync(array_values($branches));
                    Auditor::log('staff_imported', $user, $email, ['role' => $role ?: null, 'branches' => array_values($branches)]);
                });
            }

            $made[] = [$email, $name, $role ?: '-', Branch::query()->whereIn('id', $branches)->orderBy('code')->pluck('code')->implode('|'), $dry ? '-' : $password];
        }
        fclose($handle);

        if ($made !== []) {
            $this->table([__('Email'), __('Name'), __('Role'), __('Branches'), __('Temporary password')], $made);
        }
        $this->info($dry
            ? __('Dry run: :n login(s) would be made; nothing was written.', ['n' => count($made)])
            : __(':n login(s) made. Hand each password over privately; it is asked to be changed at the first sign-in and is not shown again.', ['n' => count($made)]));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** "adminmarketing01" → "Adminmarketing 01", "keuangan.pajak" → "Keuangan Pajak". */
    private static function nameFrom(string $email): string
    {
        $local = Str::before($email, '@');
        $words = preg_replace('/(\d+)$/', ' $1', str_replace(['.', '_', '-'], ' ', $local));

        return Str::title(trim((string) $words));
    }
}
