<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Company;
use App\Models\User;
use App\Notifications\AlertNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use NotificationChannels\WebPush\WebPushChannel;
use Tests\TestCase;

class AlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function alert(string $severity = 'critical'): Alert
    {
        return Alert::create([
            'company_id' => Company::factory()->create(['ticker' => 'AAPL'])->id,
            'type' => 'absolute_threshold',
            'severity' => $severity,
            'title' => 'AAPL +7.00% in 60m',
            'details' => ['change_percent' => 7.0, 'window_minutes' => 60],
            'triggered_at' => Carbon::now(),
        ]);
    }

    public function test_it_uses_the_webpush_channel_on_the_push_queue(): void
    {
        $notification = new AlertNotification($this->alert());

        $this->assertSame([WebPushChannel::class], $notification->via(new User));
        $this->assertSame('push', $notification->queue);
    }

    public function test_critical_payload_requires_interaction_and_has_click_url(): void
    {
        $alert = $this->alert('critical');
        $message = (new AlertNotification($alert))->toWebPush(new User, new AlertNotification($alert));

        $payload = $message->toArray();

        $this->assertStringContainsString('AAPL', $payload['title']);
        $this->assertTrue($payload['requireInteraction']);
        $this->assertSame("/admin/alerts?highlight={$alert->id}", $payload['data']['url']);
        $this->assertSame("alert-{$alert->company_id}", $payload['tag']);
    }

    public function test_warning_payload_does_not_require_interaction(): void
    {
        $alert = $this->alert('warning');
        $message = (new AlertNotification($alert))->toWebPush(new User, new AlertNotification($alert));

        $payload = $message->toArray();

        $this->assertArrayNotHasKey('requireInteraction', array_filter($payload, fn ($v) => $v === true));
    }
}
