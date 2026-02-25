<?php

namespace App\Services;

use App\Contracts\CustomerImportServiceInterface;
use App\Exceptions\InvalidCsvFileException;
use App\Exceptions\InvalidCsvHeaderException;
use Illuminate\Http\UploadedFile;

final class CustomerImportService implements CustomerImportServiceInterface
{
    public function import(UploadedFile $file): array
    {
        if ($file->getSize() === 0) {
            throw new InvalidCsvFileException('The uploaded file is empty.');
        }

        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new InvalidCsvFileException('Unable to read the uploaded file.');
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            throw new InvalidCsvFileException('The CSV file appears to be empty or invalid.');
        }

        $expectedHeaders = ['name', 'email', 'date_of_birth', 'annual_income'];

        if ($header !== $expectedHeaders) {
            throw new InvalidCsvHeaderException(
                'Invalid CSV header. Expected: name,email,date_of_birth,annual_income'
            );
        }

        $firstDataRow = fgetcsv($handle);

        $firstDataRowIsEmpty =
            $firstDataRow === false ||
            $firstDataRow === [null] ||
            $firstDataRow === [] ||
            (is_array($firstDataRow) && count(array_filter($firstDataRow, fn ($v) => trim((string)$v) !== '')) === 0);

        if ($firstDataRowIsEmpty) {
            throw new InvalidCsvFileException('The CSV file contains only headers and no data.');
        }

        fclose($handle);


        return [
            'total_rows_processed' => 0,
            'imported_count' => 0,
            'failed_count' => 0,
            'imported' => [],
            'errors' => [],
        ];
    }
}
