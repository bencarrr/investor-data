<?php

namespace App\Services;

use App\Models\Investor;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvestorExportService
{
    /**
     * Every investor with their age and summed investment amount.
     *
     * @return Builder<Investor>
     */
    public function query(): Builder
    {
        return Investor::query()
            ->select(['id', 'name', 'age'])
            ->withSum('investments as total_investment_amount', 'amount');
    }

    /**
     * Stream all investors as CSV, reading 1000 rows at a time to keep memory flat.
     */
    public function streamCsv(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['investor_id', 'name', 'age', 'total_investment_amount'], escape: '');

            foreach ($this->query()->lazyById(1000, 'id') as $investor) {
                fputcsv($output, [
                    $investor->id,
                    $investor->name,
                    $investor->age,
                    number_format((float) $investor->total_investment_amount, 2, '.', ''),
                ], escape: '');
            }

            fclose($output);
        }, 'investors.csv', ['Content-Type' => 'text/csv']);
    }
}
