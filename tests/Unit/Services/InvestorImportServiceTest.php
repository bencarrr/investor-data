<?php

use App\Services\InvestorImportService;
use Tests\TestCase;

uses(TestCase::class);

it('accepts the expected header regardless of case, whitespace or a byte order mark', function () {
    expect(InvestorImportService::hasValidHeader(["\u{FEFF}Investor_ID", ' name', 'AGE', 'investment_amount', 'investment_date ']))->toBeTrue();
});

it('rejects a header with missing, extra or reordered columns', function (array|false $header) {
    expect(InvestorImportService::hasValidHeader($header))->toBeFalse();
})->with([
    'empty file' => [false],
    'missing column' => [['investor_id', 'name', 'age', 'investment_amount']],
    'extra column' => [['investor_id', 'name', 'age', 'investment_amount', 'investment_date', 'email']],
    'reordered' => [['name', 'investor_id', 'age', 'investment_amount', 'investment_date']],
]);

it('accepts a valid row', function () {
    expect((new InvestorImportService)->validateRow(['1001', 'Daniel Nelson', '28', '328085.43', '13-11-2024']))->toBe([]);
});

it('rejects an invalid row', function (array $row, string $field) {
    expect((new InvestorImportService)->validateRow($row))->toHaveKey($field);
})->with([
    'missing investor id' => [['', 'Ann Lee', '30', '100.00', '01-02-2024'], 'investor_id'],
    'non-numeric investor id' => [['abc', 'Ann Lee', '30', '100.00', '01-02-2024'], 'investor_id'],
    'missing name' => [['1', '', '30', '100.00', '01-02-2024'], 'name'],
    'age below 18' => [['1', 'Ann Lee', '17', '100.00', '01-02-2024'], 'age'],
    'age above 120' => [['1', 'Ann Lee', '121', '100.00', '01-02-2024'], 'age'],
    'zero amount' => [['1', 'Ann Lee', '30', '0', '01-02-2024'], 'investment_amount'],
    'negative amount' => [['1', 'Ann Lee', '30', '-5.00', '01-02-2024'], 'investment_amount'],
    'more than two decimals' => [['1', 'Ann Lee', '30', '1.234', '01-02-2024'], 'investment_amount'],
    'ISO date' => [['1', 'Ann Lee', '30', '100.00', '2024-02-01'], 'investment_date'],
    'impossible date' => [['1', 'Ann Lee', '30', '100.00', '31-02-2024'], 'investment_date'],
    'future date' => [['1', 'Ann Lee', '30', '100.00', now()->addDay()->format('d-m-Y')], 'investment_date'],
    'too few columns' => [['1', 'Ann Lee', '30', '100.00'], 'row'],
]);
