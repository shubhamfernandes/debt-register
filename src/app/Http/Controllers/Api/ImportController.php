<?php

namespace App\Http\Controllers\Api;

use App\Contracts\CustomerImportServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportCustomersRequest;

final class ImportController extends Controller
{
    public function __construct(private readonly CustomerImportServiceInterface $importer) {}

    public function store(ImportCustomersRequest $request)
    {
        $result = $this->importer->import($request->file('file'));

        return response()->json($result);
    }
}
