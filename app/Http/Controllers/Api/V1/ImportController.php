<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImportRequest;
use App\Http\Resources\ImportResource;
use App\Jobs\ProcessInvestorImport;
use App\Models\Import;
use Illuminate\Http\JsonResponse;

class ImportController extends Controller
{
    /**
     * Store the upload and queue it for processing; clients poll the returned Location for the result.
     */
    public function store(StoreImportRequest $request): JsonResponse
    {
        $import = Import::create([
            'path' => $request->file('file')->store('imports'),
            'status' => ImportStatus::Pending,
        ]);

        ProcessInvestorImport::dispatch($import);

        return ImportResource::make($import)
            ->response()
            ->setStatusCode(202)
            ->header('Location', route('v1.imports.show', $import));
    }

    public function show(Import $import): ImportResource
    {
        return ImportResource::make($import);
    }
}
