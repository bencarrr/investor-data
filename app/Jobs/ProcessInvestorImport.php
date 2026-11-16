<?php

namespace App\Jobs;

use App\Enums\ImportStatus;
use App\Models\Import;
use App\Services\InvestorImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Runs a stored CSV upload through the importer and records the outcome on the Import.
 *
 * Retrying is safe because the importer upserts, so re-running a file never duplicates data.
 */
class ProcessInvestorImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(public Import $import) {}

    public function handle(InvestorImportService $importer): void
    {
        $this->import->update(['status' => ImportStatus::Processing]);

        $result = $importer->import(Storage::path($this->import->path));

        $this->import->update([...$result, 'status' => ImportStatus::Completed]);

        Storage::delete($this->import->path);
    }

    public function failed(?Throwable $exception): void
    {
        $this->import->update([
            'status' => ImportStatus::Failed,
            'failure_reason' => $exception?->getMessage(),
        ]);
    }
}
