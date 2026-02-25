<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportAllErrorsPerRowTest extends TestCase
{
    use RefreshDatabase;

    public function test_error_response_includes_all_errors_for_a_row_not_just_first(): void
    {
        // Row 2 triggers multiple errors:
        // - name required
        // - email invalid
        // - date_of_birth in future
        // - annual_income negative
        $csv = <<<'CSV'
name,email,date_of_birth,annual_income
,not-an-email,2999-01-01,-10
CSV;

        $response = $this->post('/api/import', [
            'file' => UploadedFile::fake()->createWithContent('all_errors.csv', $csv),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJson([
                'total_rows_processed' => 1,
                'imported_count' => 0,
                'failed_count' => 1,
            ]);

        $json = $response->json();

        $this->assertCount(1, $json['errors']);
        $this->assertSame(2, $json['errors'][0]['row_number']);

        $rowErrors = $json['errors'][0]['errors'];

        // We expect multiple errors (at least 3; likely 4)
        $this->assertTrue(count($rowErrors) >= 3);

        $fields = array_column($rowErrors, 'field');

        $this->assertContains('name', $fields);
        $this->assertContains('email', $fields);
        $this->assertContains('date_of_birth', $fields);
        $this->assertContains('annual_income', $fields);
    }
}
