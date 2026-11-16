<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\InvestorStatisticsService;
use Illuminate\Http\JsonResponse;

class StatisticsController extends Controller
{
    public function __construct(private InvestorStatisticsService $statistics) {}

    public function averageAge(): JsonResponse
    {
        return response()->json(['data' => ['average_age' => $this->statistics->averageAge()]]);
    }

    public function averageInvestmentAmount(): JsonResponse
    {
        return response()->json(['data' => ['average_investment_amount' => $this->statistics->averageInvestmentAmount()]]);
    }

    public function totalInvestments(): JsonResponse
    {
        return response()->json(['data' => ['total_investments' => $this->statistics->totalInvestments()]]);
    }
}
