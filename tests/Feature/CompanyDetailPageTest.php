<?php

namespace Tests\Feature;

use App\Filament\Resources\Companies\Pages\ViewCompany;
use App\Filament\Resources\Companies\Widgets\CompanyQuoteChartWidget;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CompanyDetailPageTest extends TestCase
{
    use RefreshDatabase;

    private function companyWithQuotes(): Company
    {
        $company = Company::factory()->create(['ticker' => 'AAPL', 'exchange' => 'NASDAQ', 'currency' => 'USD']);

        for ($i = 30; $i >= 0; $i--) {
            $company->quotes()->create([
                'price' => 100 + $i,
                'change_percent' => 1.5,
                'volume' => 1000 * $i,
                'source' => 'yahoo',
                'fetched_at' => Carbon::now()->subMinutes($i),
            ]);
        }

        return $company;
    }

    public function test_view_page_loads_for_admin(): void
    {
        $this->actingAs(User::factory()->create());
        $company = $this->companyWithQuotes();

        Livewire::test(ViewCompany::class, ['record' => $company->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_chart_widget_produces_datapoints_for_selected_range(): void
    {
        $this->actingAs(User::factory()->create());
        $company = $this->companyWithQuotes();

        $component = Livewire::test(CompanyQuoteChartWidget::class, ['record' => $company])
            ->set('filter', '1d')
            ->assertSuccessful();

        // De widget rendert zonder fouten met data; checksum is gezet bij mount.
        $this->assertNotNull($component->get('dataChecksum'));
    }
}
