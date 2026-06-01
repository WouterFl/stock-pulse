<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $exchange = fake()->randomElement(['NASDAQ', 'NYSE', 'AMS', 'LSE']);

        return [
            'ticker' => strtoupper(fake()->unique()->lexify('????')),
            'exchange' => $exchange,
            'name' => fake()->company(),
            'currency' => $exchange === 'AMS' ? 'EUR' : 'USD',
            'sector' => fake()->randomElement(['Technology', 'Financials', 'Energy', 'Healthcare', 'Consumer']),
            'industry' => fake()->randomElement(['Semiconductors', 'Software', 'Banks', 'Oil & Gas', 'Pharma']),
            'logo_url' => null,
            'is_active' => true,
            'polling_interval_seconds' => 60,
            // Standaard null → bedrijf gebruikt de globale drempel uit config/alerts.php.
            'alert_threshold_percent' => null,
            'alert_use_statistical' => false,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
