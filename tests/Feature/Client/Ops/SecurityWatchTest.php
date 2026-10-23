<?php

namespace Tests\Feature\Client\Ops;

use App\Client\Domain\Ops\Security\SecurityFinding;
use App\Client\Domain\Ops\Security\SecurityWatch;
use App\Client\Mail\SecurityAlertMessage;
use App\Domain\Pengaturan\PreferensiKey;
use App\Models\Company\AuditLog;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The eight rules over the Activity Log, their thresholds, and the command that reports each finding once per throttle. */
class SecurityWatchTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->travelTo(Carbon::parse('2026-10-23 10:00:00'));
        $this->admin = $this->actingAsAdmin();
    }

    /** @param  array<string, mixed>  $meta */
    private function row(string $action, array $meta = [], array $more = []): AuditLog
    {
        return AuditLog::query()->create(array_merge(['action' => $action, 'meta' => $meta ?: null, 'created_at' => now(), 'ip' => '203.0.113.9', 'user_id' => $this->admin->id], $more));
    }

    /** @return list<string> */
    private function rules(): array
    {
        return array_map(fn (SecurityFinding $f) => $f->rule, app(SecurityWatch::class)->findings(now()->subMinutes(20)));
    }

    public function test_a_clean_window_has_no_findings(): void
    {
        $this->row('login_failed', ['guard' => 'web'], ['reference' => 'a@example.test', 'user_id' => null]);
        $this->row('created');
        $this->assertSame([], $this->rules());
    }

    public function test_guessed_passwords_are_counted_per_address_and_per_account(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->row('login_failed', ['guard' => 'web'], ['reference' => "user{$i}@example.test", 'ip' => '198.51.100.7']);
        }
        for ($i = 0; $i < 5; $i++) {
            $this->row('login_failed', ['guard' => 'customer'], ['reference' => 'buyer@example.test', 'ip' => "10.0.0.{$i}"]);
        }
        $this->row('login_failed', ['guard' => 'web'], ['reference' => 'old@example.test', 'ip' => '198.51.100.7', 'created_at' => now()->subHour()]);

        $findings = app(SecurityWatch::class)->findings(now()->subMinutes(20));
        $this->assertSame(['failed_logins_ip', 'failed_logins_account'], array_map(fn ($f) => $f->rule, $findings));
        $this->assertStringContainsString('10 failed logins from 198.51.100.7 against 10 account(s)', $findings[0]->detail);
        $this->assertStringContainsString('5 failed logins for buyer@example.test from 5 address(es)', $findings[1]->detail);

        config(['ops.security.failed_logins_per_ip' => 11, 'ops.security.failed_logins_per_account' => 6]);
        $this->assertSame([], $this->rules(), 'thresholds are configuration');
    }

    public function test_an_administrator_from_a_new_address_is_news_but_not_their_first_login_nor_a_known_address(): void
    {
        $login = fn (string $ip, Carbon $at) => $this->row('login_succeeded', ['guard' => 'web'], ['user_id' => $this->admin->id, 'document_type' => 'user', 'document_id' => $this->admin->id, 'ip' => $ip, 'created_at' => $at]);

        $login('203.0.113.1', now()->subMinutes(5));
        $this->assertSame([], $this->rules(), 'a first login ever is not an alert');

        $login('203.0.113.1', now()->subMinutes(4));
        $this->assertSame([], $this->rules(), 'the same address again is known');

        $login('198.51.100.20', now()->subMinutes(3));
        $findings = app(SecurityWatch::class)->findings(now()->subMinutes(20));
        $this->assertSame(['admin_new_ip'], array_map(fn ($f) => $f->rule, $findings));
        $this->assertStringContainsString($this->admin->name.' from 198.51.100.20', $findings[0]->detail);

        $sales = User::factory()->create();
        $this->row('login_succeeded', ['guard' => 'web'], ['user_id' => $sales->id, 'document_type' => 'user', 'document_id' => $sales->id, 'ip' => '192.0.2.5', 'created_at' => now()->subMinutes(2)]);
        $this->row('login_succeeded', ['guard' => 'web'], ['user_id' => $sales->id, 'document_type' => 'user', 'document_id' => $sales->id, 'ip' => '192.0.2.6', 'created_at' => now()->subMinutes(1)]);
        $this->assertSame(['admin_new_ip'], $this->rules(), 'an operator from a new address is not this rule');
    }

    public function test_rights_the_segregation_switch_the_brake_a_changed_receipt_and_a_night_deletion_are_named(): void
    {
        $this->row('rights_changed', ['added' => ['x']], ['reference' => 'Finance']);
        $this->row('memberships_changed', ['before' => [], 'after' => ['Finance']], ['reference' => 'Rina']);
        $this->row('updated', ['before' => ['access_type' => 'operator'], 'after' => ['access_type' => 'administrator']], ['document_type' => 'user', 'reference' => 'Rina']);
        $this->row('updated', ['before' => ['phone' => '1'], 'after' => ['phone' => '2']], ['document_type' => 'user', 'reference' => 'Rina']);
        $this->row('preference_changed', ['key' => PreferensiKey::SegregationOfDuties->value, 'old' => true, 'new' => false], ['reference' => 'Segregation of Duties']);
        $this->row('preference_changed', ['key' => PreferensiKey::SegregationOfDuties->value, 'old' => false, 'new' => true]);
        $this->row('preference_changed', ['key' => 'other.key', 'old' => true, 'new' => false]);
        $this->row('price_list_published', ['brake' => ['21 % of prices changed']], ['reference' => '#7']);
        $this->row('price_list_published', ['brake' => []], ['reference' => '#8']);
        $this->row('updated', ['before' => ['amount' => 1000], 'after' => ['amount' => 900]], ['document_type' => 'sales_receipt', 'reference' => 'RCP-1']);
        $this->row('updated', ['before' => ['notes' => 'a'], 'after' => ['notes' => 'b']], ['document_type' => 'sales_receipt', 'reference' => 'RCP-2']);
        $this->row('deleted', ['before' => []], ['document_type' => 'sales_invoice', 'reference' => 'INV-9', 'created_at' => Carbon::parse('2026-10-23 02:30:00')]);
        $this->row('deleted', ['before' => []], ['document_type' => 'sales_invoice', 'reference' => 'INV-10', 'created_at' => Carbon::parse('2026-10-23 09:50:00')]);

        $this->travelTo(Carbon::parse('2026-10-23 10:05:00'));
        $findings = app(SecurityWatch::class)->findings(now()->subDay());
        $this->assertSame(
            ['access_changed', 'access_changed', 'access_changed', 'sod_off', 'price_brake_published', 'receipt_amount_edited', 'document_deleted_off_hours'],
            array_map(fn ($f) => $f->rule, $findings),
        );
        $this->assertStringContainsString('Rina, by '.$this->admin->name, $findings[2]->detail);
        $this->assertStringContainsString('21 % of prices changed', $findings[4]->detail);
        $this->assertStringContainsString('RCP-1 by '.$this->admin->name.': amount', $findings[5]->detail);
        $this->assertStringContainsString('INV-9', $findings[6]->detail);
    }

    public function test_the_command_reports_each_finding_once_per_throttle_and_exits_one_while_something_stands(): void
    {
        $this->artisan('central:security-watch --alert')->assertExitCode(0)->expectsOutputToContain('Nothing to report');

        $this->row('preference_changed', ['key' => PreferensiKey::SegregationOfDuties->value, 'old' => true, 'new' => false]);
        $this->artisan('central:security-watch --alert')->assertExitCode(1)->expectsOutputToContain('Segregation of duties switched off');
        Mail::assertSent(SecurityAlertMessage::class, 1);
        Mail::assertSent(SecurityAlertMessage::class, fn (SecurityAlertMessage $m) => $m->hasTo($this->admin->email) && count($m->findings) === 1);

        $this->artisan('central:security-watch --alert')->assertExitCode(1)->expectsOutputToContain('already reported');
        Mail::assertSent(SecurityAlertMessage::class, 1);

        $this->row('price_list_published', ['brake' => ['a price moved 60 %']], ['reference' => '#9']);
        $this->artisan('central:security-watch --alert')->assertExitCode(1);
        Mail::assertSent(SecurityAlertMessage::class, 2);
        Mail::assertSent(SecurityAlertMessage::class, fn (SecurityAlertMessage $m) => count($m->findings) === 1 && $m->findings[0]->rule === 'price_brake_published');

        Cache::flush();
        $this->artisan('central:security-watch')->assertExitCode(1);
        Mail::assertSent(SecurityAlertMessage::class, 2); // without --alert nothing is sent
    }

    public function test_the_watch_is_scheduled_every_ten_minutes_once_at_a_time_on_one_server(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(fn ($e) => str_contains((string) $e->command, 'central:security-watch --alert'));
        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame('*/10 * * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->onOneServer);
    }
}
