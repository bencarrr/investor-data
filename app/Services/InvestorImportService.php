<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\Investor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use SplFileObject;

class InvestorImportService
{
    public const array HEADER = ['investor_id', 'name', 'age', 'investment_amount', 'investment_date'];

    private const int CHUNK_SIZE = 1000;

    private const int MAX_REPORTED_ERRORS = 100;

    /**
     * Stream a CSV file into the investors and investments tables in chunks.
     *
     * Invalid rows are skipped and reported; a later row for the same investor and date overwrites an earlier one.
     *
     * @return array{total_rows: int, imported_rows: int, failed_rows: int, errors: list<array{line: int, errors: array<string, list<string>>}>}
     *
     * @throws InvalidArgumentException When the header row does not match the expected columns.
     */
    public function import(string $path): array
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl(escape: '');

        $header = $file->current();

        if (! self::hasValidHeader($header)) {
            throw new InvalidArgumentException('The file header must be: '.implode(',', self::HEADER).'.');
        }

        $result = ['total_rows' => 0, 'imported_rows' => 0, 'failed_rows' => 0, 'errors' => []];
        $chunk = [];

        for ($file->next(); $file->valid(); $file->next()) {
            $line = $file->key() + 1;
            $result['total_rows']++;

            $errors = $this->validateRow($file->current());

            if ($errors !== []) {
                $result['failed_rows']++;

                if (count($result['errors']) < self::MAX_REPORTED_ERRORS) {
                    $result['errors'][] = ['line' => $line, 'errors' => $errors];
                }

                continue;
            }

            $chunk[] = array_combine(self::HEADER, $file->current());

            if (count($chunk) === self::CHUNK_SIZE) {
                $result['imported_rows'] += $this->storeChunk($chunk);
                $chunk = [];
            }
        }

        $result['imported_rows'] += $this->storeChunk($chunk);

        return $result;
    }

    /**
     * @param  array<int, string|null>|false  $header
     */
    public static function hasValidHeader(array|false $header): bool
    {
        if ($header === false) {
            return false;
        }

        $normalised = array_map(fn (?string $column): string => strtolower(trim((string) $column, " \t\n\r\0\x0B\u{FEFF}")), $header);

        return $normalised === self::HEADER;
    }

    /**
     * @param  array<int, string|null>  $row
     * @return array<string, list<string>>
     */
    public function validateRow(array $row): array
    {
        if (count($row) !== count(self::HEADER)) {
            return ['row' => ['Expected '.count(self::HEADER).' columns, got '.count($row).'.']];
        }

        $validator = Validator::make(array_combine(self::HEADER, $row), [
            'investor_id' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'age' => ['required', 'integer', 'between:18,120'],
            'investment_amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'investment_date' => ['required', 'date_format:d-m-Y', 'before_or_equal:today'],
        ]);

        return $validator->errors()->toArray();
    }

    /**
     * @param  list<array<string, string>>  $rows
     */
    private function storeChunk(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        $now = now();
        $investors = [];
        $investments = [];

        foreach ($rows as $row) {
            $investorId = (int) $row['investor_id'];
            $investedOn = CarbonImmutable::createFromFormat('d-m-Y', $row['investment_date'])->toDateString();

            $investors[$investorId] = [
                'id' => $investorId,
                'name' => trim($row['name']),
                'age' => (int) $row['age'],
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $investments[$investorId.'|'.$investedOn] = [
                'investor_id' => $investorId,
                'amount' => $row['investment_amount'],
                'invested_on' => $investedOn,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($investors, $investments): void {
            Investor::upsert(array_values($investors), ['id'], ['name', 'age', 'updated_at']);
            Investment::upsert(array_values($investments), ['investor_id', 'invested_on'], ['amount', 'updated_at']);
        });

        return count($rows);
    }
}
