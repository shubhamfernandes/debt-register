<?php

namespace App\Services;

use App\Contracts\CustomerImportServiceInterface;
use App\Exceptions\InvalidCsvFileException;
use App\Exceptions\InvalidCsvHeaderException;
use App\Models\Customer;
use App\Support\CustomerRowValidation;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class CustomerImportService implements CustomerImportServiceInterface
{
    private const EXPECTED_HEADERS = ['name', 'email', 'date_of_birth', 'annual_income'];

    private const EXPECTED_COLS = 4;

    private const BATCH_SIZE = 100;

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

        // Strip UTF-8 BOM if present
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        }

        // Header normalization (trim + lowercase)
        $normalizedHeader = array_map(
            fn ($h) => strtolower(trim((string) $h)),
            $header
        );

        if ($normalizedHeader !== self::EXPECTED_HEADERS) {
            fclose($handle);
            throw new InvalidCsvHeaderException(
                'Invalid CSV header. Expected: name,email,date_of_birth,annual_income'
            );
        }

        $totalProcessed = 0;
        $rowNumber = 1;

        $structurallyValidRows = [];
        $errors = [];

        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;

            $isEmpty =
                $values === [null] ||
                $values === [] ||
                count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0;

            if ($isEmpty) {
                continue;
            }

            $totalProcessed++;

            if (count($values) !== self::EXPECTED_COLS) {
                $errors[] = [
                    'row_number' => $rowNumber,
                    'values' => $values,
                    'errors' => [
                        ['field' => 'row', 'message' => 'Malformed CSV row: wrong number of columns.'],
                    ],
                ];

                continue;
            }

            $data = array_combine(self::EXPECTED_HEADERS, $values);

            $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);

            $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));

            $structurallyValidRows[] = [
                'row_number' => $rowNumber,
                'data' => $data,
            ];
        }

        fclose($handle);

        if ($totalProcessed === 0) {
            throw new InvalidCsvFileException('The CSV file contains only headers and no data.');
        }

        // Duplicate detection within the file (case-insensitive because we normalized)
        $emailCounts = [];
        foreach ($structurallyValidRows as $row) {
            $email = (string) ($row['data']['email'] ?? '');
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

        //  One-query duplicate check against DB
        $emailsInFile = array_values(array_filter(array_keys($emailCounts), fn ($e) => $e !== ''));
        $existingEmails = [];

        if (! empty($emailsInFile)) {
            $existingEmails = Customer::query()
                ->whereIn('email', $emailsInFile)
                ->pluck('email')
                ->map(fn ($e) => strtolower((string) $e))
                ->all();
        }

        $existingSet = array_fill_keys($existingEmails, true);

        $imported = [];
        $aborted = false;
        $fatalError = null;

        //  Batch processing
        $batches = array_chunk($structurallyValidRows, self::BATCH_SIZE);

        foreach ($batches as $batchIndex => $batch) {
            if ($aborted) {
                // Mark remaining rows as unprocessed due to fatal error
                foreach ($batch as $row) {
                    $errors[] = [
                        'row_number' => $row['row_number'],
                        'values' => $this->errorValues($row['data']),
                        'errors' => [
                            ['field' => 'row', 'message' => $fatalError ?? 'Import aborted.'],
                        ],
                    ];
                }

                continue;
            }

            try {
                DB::transaction(function () use (
                    $batch,
                    &$imported,
                    &$errors,
                    &$existingSet,
                    $duplicateSet
                ) {
                    foreach ($batch as $row) {
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

                        if ($validator->fails()) {
                            foreach ($validator->errors()->messages() as $field => $messages) {
                                foreach ($messages as $message) {
                                    $rowErrors[] = ['field' => $field, 'message' => $message];
                                }
                            }
                        }

                        $email = (string) ($data['email'] ?? '');

                        // In-file duplicate check
                        if ($email !== '' && isset($duplicateSet[$email])) {
                            $rowErrors[] = ['field' => 'email', 'message' => 'Duplicate email found within the same file.'];
                        }

                        // DB duplicate check (prefetch set + updated as we insert)
                        if ($email !== '' && isset($existingSet[$email])) {
                            $rowErrors[] = ['field' => 'email', 'message' => 'Email already exists.'];
                        }

                        // Dedupe
                        $rowErrors = $this->dedupeErrors($rowErrors);

                        if (! empty($rowErrors)) {
                            $errors[] = [
                                'row_number' => $rowNum,
                                'values' => $this->errorValues($data),
                                'errors' => $rowErrors,
                            ];

                            continue;
                        }

                        try {
                            $customer = Customer::create([
                                'name' => $data['name'],
                                'email' => $email,
                                'date_of_birth' => $data['date_of_birth'],
                                'annual_income' => $data['annual_income'],
                            ]);

                            $imported[] = [
                                'id' => $customer->id,
                                'name' => $customer->name,
                            ];

                            if ($email !== '') {
                                $existingSet[$email] = true;
                            }
                        } catch (QueryException $qe) {
                            $errors[] = [
                                'row_number' => $rowNum,
                                'values' => $this->errorValues($data),
                                'errors' => [
                                    ['field' => 'row', 'message' => 'Database error while importing this row.'],
                                ],
                            ];
                        }
                    }
                });
            } catch (\Throwable $e) {

                $aborted = true;
                $fatalError = 'Database became unavailable; import aborted.';

                foreach ($batch as $row) {
                    $errors[] = [
                        'row_number' => $row['row_number'],
                        'values' => $this->errorValues($row['data']),
                        'errors' => [
                            ['field' => 'row', 'message' => $fatalError],
                        ],
                    ];
                }
            }
        }

        return [
            'total_rows_processed' => $totalProcessed,
            'imported_count' => count($imported),
            'failed_count' => count($errors),
            'aborted' => $aborted,
            'fatal_error' => $fatalError,
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

    private function dedupeErrors(array $rowErrors): array
    {
        $seen = [];
        $unique = [];

        foreach ($rowErrors as $err) {
            $field = (string) ($err['field'] ?? '');
            $message = (string) ($err['message'] ?? '');
            $key = $field.'|'.$message;

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = ['field' => $field, 'message' => $message];
        }

        return $unique;
    }
}
