<?php

namespace Tests\Feature;

use App\Events\AlertTriggered;
use App\Models\Alert;
use App\Models\Company;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AlertBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_an_alert_dispatches_broadcast_event(): void
    {
        Event::fake([AlertTriggered::class]);

        $company = Company::factory()->create(['ticker' => 'AAPL']);
        $alert = Alert::create([
            'company_id' => $company->id,
            'type' => 'absolute_threshold',
            'severity' => 'warning',
            'title' => 'AAPL +4%',
            'details' => ['change_percent' => 4.0, 'window_minutes' => 60],
            'triggered_at' => Carbon::now(),
        ]);

        Event::assertDispatched(AlertTriggered::class, fn (AlertTriggered $e) => $e->alert->is($alert));
    }

    public function test_alert_event_broadcasts_on_private_alerts_channel(): void
    {
        $company = Company::factory()->create(['ticker' => 'AAPL']);

        $alert = new Alert([
            'company_id' => $company->id,
            'type' => 'absolute_threshold',
            'severity' => 'critical',
            'title' => 'AAPL +7%',
            'details' => ['change_percent' => 7.0, 'window_minutes' => 60],
            'triggered_at' => Carbon::now(),
        ]);
        $alert->setRelation('company', $company);

        $event = new AlertTriggered($alert);
        $channels = $event->broadcastOn();

        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-alerts', $channels[0]->name);

        $payload = $event->broadcastWith();
        $this->assertSame('AAPL', $payload['ticker']);
        $this->assertSame('critical', $payload['severity']);
    }
}
