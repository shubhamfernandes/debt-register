<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportValidCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_fully_valid_csv_imports_all_rows(): void
    {
        $csv = <<<'CSV'
name,email,date_of_birth,annual_income
John Doe,john@example.com,1990-05-12,50000
Jane Smith,jane@example.com,1985-01-01,75000
CSV;

        $response = $this->postJson('/api/import', [
            'file' => UploadedFile::fake()->createWithContent('customers.csv', $csv),
        ]);

        $response->assertOk()
            ->assertJson([
                'total_rows_processed' => 2,
                'imported_count' => 2,
                'failed_count' => 0,
            ]);

        $response->assertJsonCount(2, 'imported');
        $response->assertJsonCount(0, 'errors');

        $this->assertDatabaseCount('customers', 2);
    }
}
