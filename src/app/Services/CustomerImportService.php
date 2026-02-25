<?php

namespace App\Services;

use App\Contracts\CustomerImportServiceInterface;
use Illuminate\Http\UploadedFile;

final class CustomerImportService implements CustomerImportServiceInterface
{
    public function import(UploadedFile $file): array
    {

        return [
            'total_rows_processed' => 0,
            'imported_count' => 0,
            'failed_count' => 0,
            'imported' => [],
            'errors' => [],
        ];
    }
}
