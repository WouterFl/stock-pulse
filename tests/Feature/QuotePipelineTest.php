<?php

namespace Tests\Feature;

use App\Jobs\DetectPriceMovementJob;
use App\Jobs\FetchQuoteJob;
use App\Models\Company;
use App\Services\Quotes\QuoteFetcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuotePipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_command_only_dispatches_for_due_active_companies(): void
    {
        Bus::fake();

        // Actief, nog nooit opgehaald → due.
        $due = Company::factory()->create(['is_active' => true, 'polling_interval_seconds' => 60]);

        // Actief, maar net opgehaald met 300s interval → niet due.
        $notDue = Company::factory()->create(['is_active' => true, 'polling_interval_seconds' => 300]);
        $notDue->quotes()->create(['price' => 10, 'source' => 'test', 'fetched_at' => Carbon::now()->subSeconds(30)]);

        // Inactief → wordt nooit opgehaald.
        $inactive = Company::factory()->create(['is_active' => false]);

        $this->artisan('quotes:dispatch')->assertSuccessful();

        Bus::assertDispatched(FetchQuoteJob::class, fn (FetchQuoteJob $job) => $job->company->is($due));
        Bus::assertNotDispatched(FetchQuoteJob::class, fn (FetchQuoteJob $job) => $job->company->is($notDue));
        Bus::assertNotDispatched(FetchQuoteJob::class, fn (FetchQuoteJob $job) => $job->company->is($inactive));
    }

    public function test_fetch_quote_job_stores_quote_and_dispatches_detection(): void
    {
        Bus::fake([DetectPriceMovementJob::class]);

        Http::fake([
            'query1.finance.yahoo.com/*' => Http::response([
                'chart' => ['result' => [[
                    'meta' => ['regularMarketPrice' => 200.0, 'previousClose' => 190.0],
                ]]],
            ]),
        ]);

        $company = Company::factory()->create(['ticker' => 'AAPL', 'exchange' => 'NASDAQ']);

        (new FetchQuoteJob($company))->handle(app(QuoteFetcher::class));

        $this->assertDatabaseHas('quotes', [
            'company_id' => $company->id,
            'price' => 200.0,
            'source' => 'yahoo',
        ]);

        Bus::assertDispatched(DetectPriceMovementJob::class);
    }

    public function test_fetch_quote_job_is_queued_on_quotes_queue(): void
    {
        $job = new FetchQuoteJob(Company::factory()->create());
        $this->assertSame('quotes', $job->queue);
    }
}
