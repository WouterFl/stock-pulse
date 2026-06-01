<?php

namespace Tests\Feature;

use App\Filament\Pages\Alerts;
use App\Models\Alert;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class AlertsPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeAlert(array $overrides = []): Alert
    {
        $company = Company::factory()->create();

        return Alert::create(array_merge([
            'company_id' => $company->id,
            'type' => 'absolute_threshold',
            'severity' => 'warning',
            'title' => 'Test alert',
            'details' => ['change_percent' => 4.0, 'window_minutes' => 60],
            'triggered_at' => Carbon::now(),
        ], $overrides));
    }

    public function test_page_lists_alerts_for_admin(): void
    {
        $this->actingAs(User::factory()->create());
        $alert = $this->makeAlert();

        Livewire::test(Alerts::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$alert]);
    }

    public function test_mark_as_read_action_marks_alert(): void
    {
        $this->actingAs(User::factory()->create());
        $alert = $this->makeAlert();

        $this->assertNull($alert->read_at);

        Livewire::test(Alerts::class)
            ->callTableAction('markAsRead', $alert);

        $this->assertNotNull($alert->fresh()->read_at);
    }

    public function test_navigation_badge_counts_unread(): void
    {
        $this->makeAlert();
        $this->makeAlert(['read_at' => Carbon::now()]);

        $this->assertSame('1', Alerts::getNavigationBadge());
    }
}
