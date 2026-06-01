<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TestPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PushSubscriptionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_store_subscription(): void
    {
        $this->postJson('/push-subscriptions', [])->assertUnauthorized();
    }

    public function test_user_can_store_a_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/push-subscriptions', [
                'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
                'keys' => ['p256dh' => 'pub-key', 'auth' => 'auth-token'],
                'contentEncoding' => 'aesgcm',
            ])
            ->assertOk()
            ->assertJson(['status' => 'subscribed']);

        $this->assertDatabaseHas('push_subscriptions', [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/push-subscriptions', ['endpoint' => 'x'])
            ->assertUnprocessable();
    }

    public function test_user_can_delete_a_subscription(): void
    {
        $user = User::factory()->create();
        $user->updatePushSubscription('https://fcm.test/xyz', 'pub', 'auth');

        $this->actingAs($user)
            ->deleteJson('/push-subscriptions', ['endpoint' => 'https://fcm.test/xyz'])
            ->assertOk()
            ->assertJson(['status' => 'unsubscribed']);

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://fcm.test/xyz']);
    }

    public function test_test_endpoint_sends_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/push-subscriptions/test')
            ->assertOk()
            ->assertJson(['status' => 'sent']);

        Notification::assertSentTo($user, TestPushNotification::class);
    }
}
