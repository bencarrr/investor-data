<?php

namespace App\Services;

use App\Models\Investment;
use App\Models\Investor;

class InvestorStatisticsService
{
    public function averageAge(): ?float
    {
        $average = Investor::query()->avg('age');

        return $average === null ? null : round((float) $average);
    }

    /**
     * The mean amount across every individual investment row.
     */
    public function averageInvestmentAmount(): ?float
    {
        $average = Investment::query()->avg('amount');

        return $average === null ? null : round((float) $average, 2);
    }

    public function totalInvestments(): int
    {
        return Investment::query()->count();
    }
}
