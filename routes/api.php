<?php

use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\InvestorController;
use App\Http\Controllers\Api\V1\StatisticsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {
    Route::apiResource('imports', ImportController::class)->only(['store', 'show']);

    Route::get('investors', [InvestorController::class, 'index'])->name('investors.index');

    Route::prefix('stats')->name('stats.')->controller(StatisticsController::class)->group(function () {
        Route::get('average-age', 'averageAge')->name('average-age');
        Route::get('average-investment-amount', 'averageInvestmentAmount')->name('average-investment-amount');
        Route::get('total-investments', 'totalInvestments')->name('total-investments');
    });
});
