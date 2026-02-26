<?php

namespace App\Services;

use App\Contracts\CustomerImportServiceInterface;
use App\Exceptions\InvalidCsvFileException;
use App\Exceptions\InvalidCsvHeaderException;
use App\Models\Customer;
use App\Support\CustomerRowValidation;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
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

        try {
            $this->assertValidHeader($handle);

            [$totalProcessed, $rows, $errors] = $this->parseRows($handle);

            if ($totalProcessed === 0) {
                throw new InvalidCsvFileException('The CSV file contains only headers and no data.');
            }

            [$duplicateSet, $existingSet] = $this->buildDuplicateSets($rows);

            $imported = [];
            $aborted = false;
            $fatalError = null;

            foreach (array_chunk($rows, self::BATCH_SIZE) as $batch) {
                if ($aborted) {
                    $this->markBatchAborted($batch, $errors, $fatalError);

                    continue;
                }

                foreach ($batch as $row) {
                    $rowNum = $row['row_number'];
                    $data = $row['data'];

                    $rowErrors = $this->validateRow($data, $duplicateSet, $existingSet);

                    if (! empty($rowErrors)) {
                        $errors[] = [
                            'row_number' => $rowNum,
                            'values' => $this->errorValues($data),
                            'errors' => $rowErrors,
                        ];

                        continue;
                    }

                    // Insert row (no transaction -> true partial imports)
                    try {
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

                        // Update in-memory set to prevent duplicates later in this same import run
                        $existingSet[$data['email']] = true;
                    } catch (QueryException $qe) {
                        // If DB becomes unavailable mid-import, abort remaining rows
                        $aborted = true;
                        $fatalError = 'Database became unavailable; import aborted.';

                        $errors[] = [
                            'row_number' => $rowNum,
                            'values' => $this->errorValues($data),
                            'errors' => [
                                ['field' => 'row', 'message' => $fatalError],
                            ],
                        ];

                        // Mark the rest of the current batch as aborted
                        $remaining = $this->remainingRowsInBatch($batch, $rowNum);
                        $this->markBatchAborted($remaining, $errors, $fatalError);

                        break; // move to next batches
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
        } finally {
            fclose($handle);
        }
    }

    private function assertValidHeader($handle): void
    {
        $header = fgetcsv($handle);

        if ($header === false) {
            throw new InvalidCsvFileException('The CSV file appears to be empty or invalid.');
        }

        // Strip UTF-8 BOM if present
        if (isset($header[0])) {
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        }

        $normalizedHeader = array_map(
            fn ($h) => strtolower(trim((string) $h)),
            $header
        );

        if ($normalizedHeader !== self::EXPECTED_HEADERS) {
            throw new InvalidCsvHeaderException('Invalid CSV header. Expected: name,email,date_of_birth,annual_income');
        }
    }

    /**
     * @return array{0:int,1:array<int,array{row_number:int,data:array}>,2:array<int,mixed>}
     */
    private function parseRows($handle): array
    {
        $totalProcessed = 0;
        $rowNumber = 1; // header row is 1

        $rows = [];
        $errors = [];

        while (($values = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if ($this->isEmptyRow($values)) {
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

            // Trim string fields
            $data = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $data);

            // Normalize email
            $data['email'] = strtolower(trim((string) ($data['email'] ?? '')));

            // Normalize dob and income fields
            $data['date_of_birth'] = ($data['date_of_birth'] ?? '') === '' ? null : $data['date_of_birth'];
            $data['annual_income'] = ($data['annual_income'] ?? '') === '' ? null : $data['annual_income'];

            $rows[] = [
                'row_number' => $rowNumber,
                'data' => $data,
            ];
        }

        return [$totalProcessed, $rows, $errors];
    }

    private function isEmptyRow(array $values): bool
    {
        return
            $values === [null] ||
            $values === [] ||
            count(array_filter($values, fn ($v) => trim((string) $v) !== '')) === 0;
    }

    /**
     * @param  array<int,array{row_number:int,data:array}>  $rows
     * @return array{0:array<string,bool>,1:array<string,bool>}
     */
    private function buildDuplicateSets(array $rows): array
    {
        // Count emails for in-file duplicates
        $emailCounts = [];
        foreach ($rows as $row) {
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

        // Prefetch existing emails (single query)
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

        return [$duplicateSet, $existingSet];
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<string,bool>  $duplicateSet
     * @param  array<string,bool>  $existingSet
     * @return array<int,array{field:string,message:string}>
     */
    private function validateRow(array $data, array $duplicateSet, array $existingSet): array
    {
        $validator = Validator::make(
            $data,
            CustomerRowValidation::rules(),
            CustomerRowValidation::messages()
        );

        $rowErrors = [];

        $validator->after(function ($validator) use ($data, $duplicateSet, $existingSet, &$rowErrors) {
            $email = (string) ($data['email'] ?? '');
            $emailHasIssues = $validator->errors()->has('email');

            // Only run uniqueness checks if email exists AND email itself is valid
            if ($email === '' || $emailHasIssues) {
                return;
            }

            if (isset($duplicateSet[$email])) {
                $rowErrors[] = ['field' => 'email', 'message' => 'Duplicate email found within the same file.'];
            }

            if (isset($existingSet[$email])) {
                $rowErrors[] = ['field' => 'email', 'message' => 'Email already exists.'];
            }
        });

        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $field => $messages) {
                foreach ($messages as $message) {
                    $rowErrors[] = ['field' => $field, 'message' => $message];
                }
            }
        }

        return $this->dedupeErrors($rowErrors);
    }

    /**
     * @param  array<int,array{row_number:int,data:array}>  $batch
     * @param  array<int,mixed>  $errors
     */
    private function markBatchAborted(array $batch, array &$errors, ?string $fatalError): void
    {
        foreach ($batch as $row) {
            $errors[] = [
                'row_number' => $row['row_number'],
                'values' => $this->errorValues($row['data']),
                'errors' => [
                    ['field' => 'row', 'message' => $fatalError ?? 'Import aborted.'],
                ],
            ];
        }
    }

    /**
     * @param  array<int,array{row_number:int,data:array}>  $batch
     * @return array<int,array{row_number:int,data:array}>
     */
    private function remainingRowsInBatch(array $batch, int $currentRowNumber): array
    {
        $remaining = [];
        $foundCurrent = false;

        foreach ($batch as $row) {
            if ($row['row_number'] === $currentRowNumber) {
                $foundCurrent = true;

                continue;
            }

            if ($foundCurrent) {
                $remaining[] = $row;
            }
        }

        return $remaining;
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

    /**
     * @param  array<int,array{field:string,message:string}>  $rowErrors
     * @return array<int,array{field:string,message:string}>
     */
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
