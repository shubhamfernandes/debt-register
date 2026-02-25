<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface CustomerImportServiceInterface
{
    public function import(UploadedFile $file): array;
}
