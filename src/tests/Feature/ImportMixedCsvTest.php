<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportMixedCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_csv_imports_valid_rows_and_reports_invalid_with_correct_row_numbers(): void
    {
        // Header row = 1
        // Data rows start at row 2
        $csv = <<<CSV
name,email,date_of_birth,annual_income
Valid User,valid1@example.com,1990-01-01,50000
,missingname@example.com,1990-01-01,1000
Bad Email,bad-email,1990-01-01,1000
Future DOB,future@example.com,2999-01-01,1000
Negative Income,neg@example.com,1990-01-01,-5
CSV;

        $response = $this->postJson('/api/import', [
            'file' => UploadedFile::fake()->createWithContent('mixed.csv', $csv),
        ]);

        $response->assertOk()
            ->assertJson([
                'total_rows_processed' => 5,
                'imported_count' => 1,
                'failed_count' => 4,
            ]);

        // Imported should contain the valid row
        $response->assertJsonCount(1, 'imported');

        $json = $response->json();

        // Collect row_numbers returned in errors
        $rowNumbers = array_column($json['errors'], 'row_number');

        // Expected invalid rows:
        // Row 3 = missing name (2nd data row)
        // Row 4 = bad email
        // Row 5 = future dob
        // Row 6 = negative income
        $this->assertContains(3, $rowNumbers);
        $this->assertContains(4, $rowNumbers);
        $this->assertContains(5, $rowNumbers);
        $this->assertContains(6, $rowNumbers);

        // DB should only have the 1 valid customer
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_mixed_csv_skips_empty_rows_and_allows_optional_fields_blank(): void
{
    $csv = <<<CSV
name,email,date_of_birth,annual_income
Valid One,valid1@example.com,,

Valid Two,valid2@example.com,1990-01-01,
Bad Email,bad-email,,
CSV;

    $response = $this->postJson('/api/import', [
        'file' => UploadedFile::fake()->createWithContent('mixed2.csv', $csv),
    ]);

    $response->assertOk()
        ->assertJson([
            // Non-empty data rows: Valid One, Valid Two, Bad Email = 3 processed
            'total_rows_processed' => 3,
            'imported_count' => 2,
            'failed_count' => 1,
        ]);

    $json = $response->json();

    // Bad email should be on row 5:
    // row1 header
    // row2 valid one
    // row3 empty row (skipped)
    // row4 valid two
    // row5 bad email
    $rowNumbers = array_column($json['errors'], 'row_number');
    $this->assertContains(5, $rowNumbers);

    $this->assertDatabaseCount('customers', 2);
}

public function test_mixed_csv_reports_malformed_rows_and_still_imports_valid_rows(): void
{
    $csv = <<<CSV
name,email,date_of_birth,annual_income
Valid One,valid1@example.com,1990-01-01,1000
Malformed Row,malformed@example.com
Valid Two,valid2@example.com,1991-01-01,2000
CSV;

    $response = $this->postJson('/api/import', [
        'file' => UploadedFile::fake()->createWithContent('mixed3.csv', $csv),
    ]);

    $response->assertOk()
        ->assertJson([
            'total_rows_processed' => 3,
            'imported_count' => 2,
            'failed_count' => 1,
        ]);

    $json = $response->json();
    $this->assertCount(1, $json['errors']);

    // Malformed row is the 2nd data row, so row number = 3
    $this->assertSame(3, $json['errors'][0]['row_number']);

    $this->assertDatabaseCount('customers', 2);
}
}
