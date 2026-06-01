<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Company;
use App\Models\User;
use App\Notifications\AlertNotification;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AlertPushTest extends TestCase
{
    use RefreshDatabase;

    private function subscribedUser(array $prefs = []): User
    {
        $user = User::factory()->create(['notification_preferences' => $prefs ?: null]);
        $user->updatePushSubscription('https://fcm.test/'.uniqid(), 'pub', 'auth');

        return $user;
    }

    private function makeAlert(string $severity = 'critical', string $type = 'absolute_threshold'): Alert
    {
        return Alert::create([
            'company_id' => Company::factory()->create()->id,
            'type' => $type,
            'severity' => $severity,
            'title' => 'Test',
            'details' => ['change_percent' => 7.0, 'window_minutes' => 60],
            'triggered_at' => Carbon::now(),
        ]);
    }

    public function test_critical_alert_pushes_to_subscribed_users(): void
    {
        Notification::fake();
        $user = $this->subscribedUser();

        $this->makeAlert('critical');

        Notification::assertSentTo($user, AlertNotification::class);
    }

    public function test_info_alert_is_not_pushed(): void
    {
        Notification::fake();
        $this->subscribedUser();

        $this->makeAlert('info', 'news_spike');

        Notification::assertNothingSent();
    }

    public function test_user_without_subscription_is_not_pushed(): void
    {
        Notification::fake();
        User::factory()->create(); // geen subscription

        $this->makeAlert('critical');

        Notification::assertNothingSent();
    }

    public function test_disabled_category_is_not_pushed(): void
    {
        Notification::fake();
        $this->subscribedUser(['types' => ['statistical_outlier' => false]]);

        $this->makeAlert('critical', 'statistical_outlier');

        Notification::assertNothingSent();
    }

    public function test_quiet_hours_block_push(): void
    {
        $prefs = NotificationPreferences::fromArray([
            'quiet_hours' => ['enabled' => true, 'start' => '00:00', 'end' => '23:59'],
        ]);

        $alert = $this->makeAlert('critical');

        $this->assertFalse($prefs->allowsPush($alert, Carbon::createFromTime(12, 0)));
    }

    public function test_min_severity_filters_warning(): void
    {
        $prefs = NotificationPreferences::fromArray(['min_severity' => 'critical']);
        $warning = $this->makeAlert('warning');

        $this->assertFalse($prefs->allowsPush($warning));
    }
}
