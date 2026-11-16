<?php

use App\Models\Investment;
use App\Models\Investor;

beforeEach(function () {
    $ann = Investor::factory()->create(['age' => 30]);
    $bob = Investor::factory()->create(['age' => 45]);

    Investment::factory()->for($ann)->create(['amount' => '100.00', 'invested_on' => '2024-01-01']);
    Investment::factory()->for($ann)->create(['amount' => '200.00', 'invested_on' => '2024-02-01']);
    Investment::factory()->for($bob)->create(['amount' => '600.00', 'invested_on' => '2024-01-01']);
});

it('returns the average age across all investors, rounded to a whole number', function () {
    $this->getJson(route('v1.stats.average-age'))
        ->assertOk()
        ->assertExactJson(['data' => ['average_age' => 38]]);
});

it('returns the average amount across all investments', function () {
    $this->getJson(route('v1.stats.average-investment-amount'))
        ->assertOk()
        ->assertExactJson(['data' => ['average_investment_amount' => 300]]);
});

it('returns the total number of investments', function () {
    $this->getJson(route('v1.stats.total-investments'))
        ->assertOk()
        ->assertExactJson(['data' => ['total_investments' => 3]]);
});

it('returns null averages and a zero total when there is no data', function () {
    Investor::query()->delete();

    $this->getJson(route('v1.stats.average-age'))->assertExactJson(['data' => ['average_age' => null]]);
    $this->getJson(route('v1.stats.average-investment-amount'))->assertExactJson(['data' => ['average_investment_amount' => null]]);
    $this->getJson(route('v1.stats.total-investments'))->assertExactJson(['data' => ['total_investments' => 0]]);
});
