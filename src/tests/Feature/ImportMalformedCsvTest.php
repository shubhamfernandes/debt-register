<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportMalformedCsvTest extends TestCase
{
    use RefreshDatabase;

    public function test_malformed_row_is_reported_and_valid_rows_still_import(): void
    {
        $csv = <<<'CSV'
name,email,date_of_birth,annual_income
Valid One,valid1@example.com,1990-01-01,1000
Malformed Row,malformed@example.com
Valid Two,valid2@example.com,1991-01-01,2000
CSV;

        $response = $this->post('/api/import', [
            'file' => UploadedFile::fake()->createWithContent('malformed.csv', $csv),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJson([
                'total_rows_processed' => 3,
                'imported_count' => 2,
                'failed_count' => 1,
            ]);

        $json = $response->json();

        // Only one malformed row
        $this->assertCount(1, $json['errors']);

        $this->assertSame(3, $json['errors'][0]['row_number']);

        $this->assertSame(
            'Malformed CSV row: wrong number of columns.',
            $json['errors'][0]['errors'][0]['message']
        );

        $this->assertDatabaseCount('customers', 2);
    }
}
