<?php

namespace Tests\Feature;

use App\Filament\Resources\Companies\Pages\CreateCompany;
use App\Filament\Resources\Companies\Pages\ListCompanies;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CompanyResourceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_ticker_and_exchange_are_uppercased_on_save(): void
    {
        $company = Company::create([
            'ticker' => 'aapl',
            'exchange' => 'nasdaq',
            'name' => 'Apple Inc.',
            'currency' => 'usd',
        ]);

        $this->assertSame('AAPL', $company->fresh()->ticker);
        $this->assertSame('NASDAQ', $company->fresh()->exchange);
        $this->assertSame('USD', $company->fresh()->currency);
    }

    public function test_guest_cannot_access_company_list(): void
    {
        $this->get('/admin/companies')->assertRedirect();
    }

    public function test_admin_can_list_companies(): void
    {
        $this->actingAs($this->admin());
        Company::factory()->count(3)->create();

        Livewire::test(ListCompanies::class)
            ->assertSuccessful()
            ->assertCountTableRecords(3);
    }

    public function test_admin_can_create_company_with_uppercase_ticker(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateCompany::class)
            ->fillForm([
                'ticker' => 'msft',
                'exchange' => 'nasdaq',
                'name' => 'Microsoft Corporation',
                'currency' => 'usd',
                'polling_interval_seconds' => 60,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('companies', [
            'ticker' => 'MSFT',
            'exchange' => 'NASDAQ',
            'name' => 'Microsoft Corporation',
        ]);
    }
}
