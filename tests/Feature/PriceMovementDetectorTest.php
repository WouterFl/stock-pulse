<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Quote;
use App\Services\Alerts\PriceMovementDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PriceMovementDetectorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'alerts.default_threshold_percent' => 3.0,
            'alerts.default_window_minutes' => 60,
            'alerts.cooldown_minutes' => 60,
            'alerts.critical_multiplier' => 2.0,
            'alerts.statistical_periods' => 20,
            'alerts.statistical_sigma' => 2.0,
        ]);
    }

    private function quote(Company $c, float $price, int $minsAgo, ?float $changePct = null): Quote
    {
        return $c->quotes()->create([
            'price' => $price,
            'change_percent' => $changePct,
            'source' => 'test',
            'fetched_at' => Carbon::now()->subMinutes($minsAgo),
        ]);
    }

    public function test_no_alert_below_threshold(): void
    {
        $c = Company::factory()->create();
        $this->quote($c, 100, 30);
        $latest = $this->quote($c, 102, 0); // +2% < 3%

        $alerts = (new PriceMovementDetector)->detect($c, $latest);
        $this->assertCount(0, $alerts);
    }

    public function test_absolute_alert_warning_above_threshold(): void
    {
        $c = Company::factory()->create();
        $this->quote($c, 100, 30);
        $latest = $this->quote($c, 104, 0); // +4% >= 3%

        $alerts = (new PriceMovementDetector)->detect($c, $latest);

        $this->assertCount(1, $alerts);
        $this->assertSame('absolute_threshold', $alerts->first()->type);
        $this->assertSame('warning', $alerts->first()->severity);
        $this->assertEquals(4.0, $alerts->first()->details['change_percent']);
    }

    public function test_absolute_alert_critical_above_multiplier(): void
    {
        $c = Company::factory()->create();
        $this->quote($c, 100, 30);
        $latest = $this->quote($c, 107, 0); // +7% >= 3% * 2

        $alerts = (new PriceMovementDetector)->detect($c, $latest);
        $this->assertSame('critical', $alerts->first()->severity);
    }

    public function test_cooldown_prevents_second_absolute_alert(): void
    {
        $c = Company::factory()->create();
        $this->quote($c, 100, 30);
        $latest = $this->quote($c, 104, 0);

        $detector = new PriceMovementDetector;
        $this->assertCount(1, $detector->detect($c, $latest));
        // Tweede detectie binnen cooldown → niets.
        $this->assertCount(0, $detector->detect($c, $latest));
    }

    public function test_statistical_only_runs_when_enabled(): void
    {
        $c = Company::factory()->create(['alert_use_statistical' => false, 'alert_threshold_percent' => 100]);
        // Stabiele kleine bewegingen, dan een uitschieter.
        for ($i = 20; $i >= 1; $i--) {
            $this->quote($c, 100, $i + 1, 0.1);
        }
        $latest = $this->quote($c, 100, 0, 5.0); // grote z-score

        // Statistisch staat uit én absolute drempel is 100% → geen alerts.
        $this->assertCount(0, (new PriceMovementDetector)->detect($c, $latest));

        // Nu aanzetten.
        $c->update(['alert_use_statistical' => true]);
        $alerts = (new PriceMovementDetector)->detect($c, $latest);
        $this->assertCount(1, $alerts);
        $this->assertSame('statistical_outlier', $alerts->first()->type);
    }
}
