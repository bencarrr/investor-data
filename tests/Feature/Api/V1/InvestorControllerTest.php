<?php

use App\Models\Investment;
use App\Models\Investor;

it('lists investors with their age and summed investment amount', function () {
    $ann = Investor::factory()->create(['id' => 1, 'name' => 'Ann Lee', 'age' => 30]);
    Investor::factory()->create(['id' => 2, 'name' => 'Bob Ray', 'age' => 45]);
    Investment::factory()->for($ann)->create(['amount' => '100.25', 'invested_on' => '2024-01-01']);
    Investment::factory()->for($ann)->create(['amount' => '200.50', 'invested_on' => '2024-02-01']);

    $this->getJson(route('v1.investors.index'))
        ->assertOk()
        ->assertJsonPath('data', [
            ['investor_id' => 1, 'name' => 'Ann Lee', 'age' => 30, 'total_investment_amount' => '300.75'],
            ['investor_id' => 2, 'name' => 'Bob Ray', 'age' => 45, 'total_investment_amount' => '0.00'],
        ]);
});

it('paginates investors with a cursor', function () {
    foreach ([1, 2, 3] as $id) {
        Investor::factory()->create(['id' => $id]);
    }

    $firstPage = $this->getJson(route('v1.investors.index', ['per_page' => 2]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.investor_id', 2);

    $this->getJson($firstPage->json('links.next'))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.investor_id', 3)
        ->assertJsonPath('links.next', null);
});

it('returns 422 when per_page is out of range', function () {
    $this->getJson(route('v1.investors.index', ['per_page' => 1001]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page' => 'The per page field must be between 1 and 1000.']);
});

it('streams every investor as CSV when requested', function (array $query, array $headers) {
    $ann = Investor::factory()->create(['id' => 1, 'name' => 'Ann Lee', 'age' => 30]);
    Investor::factory()->create(['id' => 2, 'name' => 'Bob Ray', 'age' => 45]);
    Investment::factory()->for($ann)->create(['amount' => '100.25']);

    $response = $this->get(route('v1.investors.index', $query), $headers);

    $response->assertOk()->assertDownload('investors.csv');
    expect($response->headers->get('Content-Type'))->toStartWith('text/csv')
        ->and($response->streamedContent())->toBe(
            "investor_id,name,age,total_investment_amount\n1,\"Ann Lee\",30,100.25\n2,\"Bob Ray\",45,0.00\n"
        );
})->with([
    'format query parameter' => [['format' => 'csv'], []],
    'Accept header' => [[], ['Accept' => 'text/csv']],
]);
