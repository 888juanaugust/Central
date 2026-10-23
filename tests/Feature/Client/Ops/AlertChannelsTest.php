<?php

namespace Tests\Feature\Client\Ops;

use App\Client\Domain\Ops\Alerts\Alert;
use App\Client\Domain\Ops\Alerts\AlertDispatcher;
use App\Client\Domain\Ops\Alerts\NullSender;
use App\Client\Domain\Ops\Alerts\WhatsAppChannel;
use App\Client\Domain\Ops\Alerts\WhatsAppSender;
use App\Client\Domain\Ops\Health\OpsCheck;
use App\Client\Mail\SystemAlertMessage;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/** One alert, every configured channel: mail always, WhatsApp once a provider and numbers are set, each failing on its own. */
class AlertChannelsTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->admin = $this->actingAsAdmin();
    }

    private function alert(): Alert
    {
        return Alert::health([OpsCheck::critical('disk', 'Disk space', '3 % free')]);
    }

    public function test_without_a_provider_only_the_mail_goes_and_the_sender_is_the_null_one(): void
    {
        config(['ops.whatsapp.provider' => null, 'ops.whatsapp.recipients' => '+628123456789']);

        $this->assertInstanceOf(NullSender::class, app(WhatsAppSender::class));
        $this->assertFalse(WhatsAppChannel::configured());
        $this->assertSame(['mail'], app(AlertDispatcher::class)->dispatch($this->alert()));
        Mail::assertSent(SystemAlertMessage::class, fn (SystemAlertMessage $m) => $m->hasTo($this->admin->email));
    }

    public function test_with_a_provider_and_numbers_the_text_reaches_every_number_too(): void
    {
        config(['ops.whatsapp.provider' => 'fonnte', 'ops.whatsapp.recipients' => '+62 812-3456-789, +628987654321,, +628987654321']);
        $sender = new class implements WhatsAppSender
        {
            /** @var array<int, array{string, string}> */
            public array $sent = [];

            public function send(string $to, string $text): void
            {
                $this->sent[] = [$to, $text];
            }
        };
        $this->app->instance(WhatsAppSender::class, $sender);

        $this->assertSame(['+628123456789', '+628987654321'], WhatsAppChannel::recipients(), 'spaces and dashes dropped, duplicates once');
        $this->assertSame(['mail', 'whatsapp'], app(AlertDispatcher::class)->dispatch($this->alert()));
        $this->assertCount(2, $sender->sent);
        $this->assertStringContainsString('[CRITICAL] System health', $sender->sent[0][1]);
        $this->assertStringContainsString('Disk space. 3 % free', $sender->sent[0][1]);
        Mail::assertSent(SystemAlertMessage::class);
    }

    public function test_a_sender_that_throws_does_not_stop_the_mail(): void
    {
        config(['ops.whatsapp.provider' => 'fonnte', 'ops.whatsapp.recipients' => '+628123456789']);
        $this->app->instance(WhatsAppSender::class, new class implements WhatsAppSender
        {
            public function send(string $to, string $text): void
            {
                throw new RuntimeException('provider down');
            }
        });

        $this->assertSame(['mail'], app(AlertDispatcher::class)->dispatch($this->alert()));
        Mail::assertSent(SystemAlertMessage::class);
    }

    public function test_the_plain_text_names_the_severity_the_lines_and_the_application(): void
    {
        config(['app.url' => 'https://erp.example.test']);
        $text = $this->alert()->text();

        $this->assertStringStartsWith('[CRITICAL] System health', $text);
        $this->assertStringContainsString("\n- CRITICAL: Disk space. 3 % free", $text);
        $this->assertStringEndsWith('https://erp.example.test', $text);
    }
}
