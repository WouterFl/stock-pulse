<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PruneQuotesTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prunes_only_quotes_older_than_the_given_days(): void
    {
        $company = Company::factory()->create();

        $old = $company->quotes()->create(['price' => 10, 'source' => 'test', 'fetched_at' => Carbon::now()->subDays(45)]);
        $recent = $company->quotes()->create(['price' => 11, 'source' => 'test', 'fetched_at' => Carbon::now()->subDays(5)]);

        $this->artisan('quotes:prune', ['--days' => 30])->assertSuccessful();

        $this->assertDatabaseMissing('quotes', ['id' => $old->id]);
        $this->assertDatabaseHas('quotes', ['id' => $recent->id]);
    }
}
