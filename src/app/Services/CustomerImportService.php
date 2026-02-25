<?php

namespace App\Services;

use App\Contracts\CustomerImportServiceInterface;
use App\Exceptions\InvalidCsvFileException;
use App\Exceptions\InvalidCsvHeaderException;
use App\Models\Customer;
use App\Support\CustomerRowValidation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class CustomerImportService implements CustomerImportServiceInterface
{
    private const EXPECTED_HEADERS = ['name', 'email', 'date_of_birth', 'annual_income'];
    private const EXPECTED_COLS = 4;

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
            fclose($handle);
            throw new InvalidCsvFileException('The CSV file appears to be empty or invalid.');
        }

        if ($header !== self::EXPECTED_HEADERS) {
            fclose($handle);
            throw new InvalidCsvHeaderException('Invalid CSV header. Expected: name,email,date_of_birth,annual_income');
        }

        $totalProcessed = 0; // non-empty data rows processed (malformed included, empty skipped)
        $rowNumber = 1;      // header row is 1

        $structurallyValidRows = []; // rows with correct column count (to be validated later)
        $errors = [];

        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;

            // Skip empty rows silently (not counted as errors, not counted as processed)
            $isEmpty =
                $values === [null] ||
                $values === [] ||
                count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0;

            if ($isEmpty) {
                continue;
            }

            $totalProcessed++;

            // Malformed row: wrong number of columns
            if (count($values) !== self::EXPECTED_COLS) {
                $errors[] = [
                    'row_number' => $rowNumber,
                    'values_raw' => $values,
                    'errors' => [
                        ['field' => 'row', 'message' => 'Malformed CSV row: wrong number of columns.'],
                    ],
                ];
                continue;
            }

            $data = array_combine(self::EXPECTED_HEADERS, $values);

            // Trim string fields
            $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);

            $structurallyValidRows[] = [
                'row_number' => $rowNumber,
                'data' => $data,
            ];
        }

        fclose($handle);

        // If there were no non-empty data rows at all, treat as invalid file (headers only)
        if ($totalProcessed === 0) {
            throw new InvalidCsvFileException('The CSV file contains only headers and no data.');
        }

        // Detect duplicates within the same file (both rows flagged) — case-insensitive
        $emailCounts = [];
        foreach ($structurallyValidRows as $row) {
            $email = strtolower((string) ($row['data']['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $emailCounts[$email] = ($emailCounts[$email] ?? 0) + 1;
        }

        $duplicateSet = [];
        foreach ($emailCounts as $email => $count) {
            if ($count > 1) {
                $duplicateSet[$email] = true;
            }
        }

        $imported = [];

        DB::transaction(function () use ($structurallyValidRows, $duplicateSet, &$errors, &$imported) {
            foreach ($structurallyValidRows as $row) {
                $rowNum = $row['row_number'];
                $data = $row['data'];

                // Normalise empty optional fields to null
                $data['date_of_birth'] = ($data['date_of_birth'] ?? '') === '' ? null : $data['date_of_birth'];
                $data['annual_income'] = ($data['annual_income'] ?? '') === '' ? null : $data['annual_income'];

                $validator = Validator::make(
                    $data,
                    CustomerRowValidation::rules(),
                    CustomerRowValidation::messages()
                );

                $rowErrors = [];

                // Collect all validator errors (not just the first)
                if ($validator->fails()) {
                    foreach ($validator->errors()->messages() as $field => $messages) {
                        foreach ($messages as $message) {
                            $rowErrors[] = ['field' => $field, 'message' => $message];
                        }
                    }
                }

                // In-file duplicate check (both rows flagged)
                $emailLower = strtolower((string) ($data['email'] ?? ''));
                if ($emailLower !== '' && isset($duplicateSet[$emailLower])) {
                    $rowErrors[] = ['field' => 'email', 'message' => 'Duplicate email found within the same file.'];
                }

                // If any errors, report them and do not import this row
                if (!empty($rowErrors)) {
                    $errors[] = [
                        'row_number' => $rowNum,
                        'values' => $this->errorValues($data),
                        'errors' => $rowErrors,
                    ];
                    continue;
                }

                // Import valid row
                $customer = Customer::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'date_of_birth' => $data['date_of_birth'],
                    'annual_income' => $data['annual_income'],
                ]);

                $imported[] = [
                    'id' => $customer->id,
                    'name' => $customer->name,
                ];
            }
        });

        return [
            'total_rows_processed' => $totalProcessed,
            'imported_count' => count($imported),
            'failed_count' => count($errors),
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    private function errorValues(array $data): array
    {

        return [
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'annual_income' => $data['annual_income'] ?? null,
        ];
    }
}
