<?php

namespace Tests\Feature;

use App\Console\Commands\CleanupStalePushSubscriptionsCommand;
use App\Listeners\CleanupExpiredSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Minishlink\WebPush\MessageSentReport;
use Mockery;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\PushSubscription;
use NotificationChannels\WebPush\WebPushMessageInterface;
use Tests\TestCase;

class PushCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_subscription_is_deleted_on_failure(): void
    {
        $user = User::factory()->create();
        $user->updatePushSubscription('https://fcm.test/expired', 'pub', 'auth');
        $subscription = PushSubscription::where('endpoint', 'https://fcm.test/expired')->first();

        $report = Mockery::mock(MessageSentReport::class);
        $report->shouldReceive('isSubscriptionExpired')->andReturnTrue();

        $event = new NotificationFailed($report, $subscription, Mockery::mock(WebPushMessageInterface::class));

        (new CleanupExpiredSubscription)->handle($event);

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://fcm.test/expired']);
    }

    public function test_non_expired_failure_keeps_subscription(): void
    {
        $user = User::factory()->create();
        $user->updatePushSubscription('https://fcm.test/temp', 'pub', 'auth');
        $subscription = PushSubscription::where('endpoint', 'https://fcm.test/temp')->first();

        $report = Mockery::mock(MessageSentReport::class);
        $report->shouldReceive('isSubscriptionExpired')->andReturnFalse();

        (new CleanupExpiredSubscription)->handle(
            new NotificationFailed($report, $subscription, Mockery::mock(WebPushMessageInterface::class))
        );

        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://fcm.test/temp']);
    }

    public function test_stale_command_removes_old_subscriptions(): void
    {
        $user = User::factory()->create();
        $user->updatePushSubscription('https://fcm.test/old', 'pub', 'auth');
        $user->updatePushSubscription('https://fcm.test/fresh', 'pub', 'auth');

        PushSubscription::where('endpoint', 'https://fcm.test/old')
            ->update(['updated_at' => Carbon::now()->subDays(120)]);

        $this->artisan(CleanupStalePushSubscriptionsCommand::class, ['--days' => 90])->assertSuccessful();

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://fcm.test/old']);
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://fcm.test/fresh']);
    }
}
