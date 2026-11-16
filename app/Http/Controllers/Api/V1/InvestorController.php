<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexInvestorsRequest;
use App\Http\Resources\InvestorResource;
use App\Services\InvestorExportService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvestorController extends Controller
{
    public function index(IndexInvestorsRequest $request, InvestorExportService $investors): AnonymousResourceCollection|StreamedResponse
    {
        if ($request->wantsCsv()) {
            return $investors->streamCsv();
        }

        return InvestorResource::collection(
            $investors->query()->orderBy('id')->cursorPaginate($request->integer('per_page', 100))->withQueryString()
        );
    }
}
