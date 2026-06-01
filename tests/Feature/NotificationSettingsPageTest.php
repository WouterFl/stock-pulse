<?php

namespace Tests\Feature;

use App\Filament\Pages\NotificationSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_loads_with_defaults(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(NotificationSettings::class)
            ->assertSuccessful()
            ->assertFormSet(['push_enabled' => true, 'min_severity' => 'warning']);
    }

    public function test_user_can_save_preferences(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(NotificationSettings::class)
            ->fillForm([
                'push_enabled' => true,
                'min_severity' => 'critical',
                'types' => ['news_spike' => false],
                'quiet_hours' => ['enabled' => true, 'start' => '23:00', 'end' => '07:00'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $prefs = $user->fresh()->notificationPreferences();
        $this->assertSame('critical', $prefs->toArray()['min_severity']);
        $this->assertTrue($prefs->toArray()['quiet_hours']['enabled']);
        $this->assertFalse($prefs->toArray()['types']['news_spike']);
    }

    public function test_user_can_delete_a_device_subscription(): void
    {
        $user = User::factory()->create();
        $user->updatePushSubscription('https://fcm.test/dev1', 'pub', 'auth');
        $subId = $user->pushSubscriptions()->first()->id;
        $this->actingAs($user);

        Livewire::test(NotificationSettings::class)
            ->call('deleteSubscription', $subId);

        $this->assertDatabaseMissing('push_subscriptions', ['id' => $subId]);
    }
}
