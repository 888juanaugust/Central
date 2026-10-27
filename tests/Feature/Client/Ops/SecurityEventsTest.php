<?php

namespace Tests\Feature\Client\Ops;

use App\Client\Domain\Ops\Security\RecordsAuthEvents;
use App\Models\Company\AuditLog;
use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\Feature\Client\Support\Buyer;
use Tests\Feature\Client\Support\OrderFlow;
use Tests\TestCase;

/** Every sign-in attempt on either guard lands in the Activity Log and in the security log, with the address and the email tried. */
class SecurityEventsTest extends TestCase
{
    use Buyer, OrderFlow;

    private string $securityLog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepareOrderFlow();
        auth()->forgetUser();
        $this->securityLog = storage_path('framework/testing/security-'.uniqid().'.log');
        config()->set('logging.channels.'.RecordsAuthEvents::CHANNEL.'.driver', 'single');
        config()->set('logging.channels.'.RecordsAuthEvents::CHANNEL.'.path', $this->securityLog);
    }

    protected function tearDown(): void
    {
        @unlink($this->securityLog);
        parent::tearDown();
    }

    /** @return list<array<string, mixed>> */
    private function securityLines(): array
    {
        $lines = array_filter(explode("\n", (string) @file_get_contents($this->securityLog)));

        return array_values(array_map(fn (string $l) => json_decode($l, true, flags: JSON_THROW_ON_ERROR), $lines));
    }

    public function test_a_wrong_and_then_a_right_staff_password_are_both_on_the_record(): void
    {
        $user = User::factory()->create(['email' => 'rina@example.test', 'is_active' => true]);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)->fillForm(['email' => 'rina@example.test', 'password' => 'wrong'])->call('authenticate')->assertHasFormErrors(['email']);

        $failed = AuditLog::query()->where('action', 'login_failed')->sole();
        $this->assertNull($failed->user_id, 'nobody is signed in when a password fails');
        $this->assertSame('rina@example.test', $failed->reference);
        $this->assertSame('user', $failed->document_type);
        $this->assertSame($user->id, (int) $failed->document_id);
        $this->assertSame('web', $failed->meta['guard']);
        $this->assertSame('127.0.0.1', $failed->meta['ip']);

        Livewire::test(Login::class)->fillForm(['email' => 'rina@example.test', 'password' => 'password'])->call('authenticate')->assertHasNoFormErrors();

        $succeeded = AuditLog::query()->where('action', 'login_succeeded')->sole();
        $this->assertSame($user->id, (int) $succeeded->user_id, 'the actor is the user who signed in, though the guard had not set them yet');
        $this->assertSame($user->id, (int) $succeeded->document_id);
        $this->assertSame('web', $succeeded->meta['guard']);

        $lines = $this->securityLines();
        $this->assertCount(2, $lines);
        $this->assertSame('login_failed', $lines[0]['context']['event']);
        $this->assertSame('rina@example.test', $lines[0]['context']['email']);
        $this->assertSame('127.0.0.1', $lines[0]['context']['ip']);
        $this->assertSame('web', $lines[0]['context']['guard']);
        $this->assertSame('login_succeeded', $lines[1]['context']['event']);
        $this->assertSame($user->id, $lines[1]['context']['user_id']);
        $this->assertArrayHasKey('datetime', $lines[1], 'CrowdSec reads the time from the line');
    }

    public function test_a_buyer_on_the_portal_guard_is_recorded_the_same_way_without_a_staff_actor(): void
    {
        $buyer = $this->buyer();
        Filament::setCurrentPanel(Filament::getPanel('portal'));

        Livewire::test(Login::class)->fillForm(['email' => $buyer->email, 'password' => 'wrong'])->call('authenticate')->assertHasFormErrors(['email']);
        Livewire::test(Login::class)->fillForm(['email' => $buyer->email, 'password' => 'secret-password'])->call('authenticate')->assertHasNoFormErrors();

        $failed = AuditLog::query()->where('action', 'login_failed')->sole();
        $this->assertSame('customer', $failed->meta['guard']);
        $this->assertSame('customer_user', $failed->document_type);
        $succeeded = AuditLog::query()->where('action', 'login_succeeded')->sole();
        $this->assertSame('customer', $succeeded->meta['guard']);
        $this->assertNull($succeeded->user_id, 'a buyer is not a staff user');
        $this->assertSame($buyer->id, (int) $succeeded->document_id);
        $this->assertSame(['login_failed', 'login_succeeded'], array_column(array_column($this->securityLines(), 'context'), 'event'));
    }

    public function test_an_unknown_email_is_recorded_with_no_subject(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Login::class)->fillForm(['email' => 'nobody@example.test', 'password' => 'x'])->call('authenticate')->assertHasFormErrors(['email']);

        $failed = AuditLog::query()->where('action', 'login_failed')->sole();
        $this->assertNull($failed->document_type);
        $this->assertSame('nobody@example.test', $failed->reference);
    }
}
