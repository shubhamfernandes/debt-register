<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportValidationRulesTest extends TestCase
{
    use RefreshDatabase;

    private function postCsv(string $csv)
    {
        return $this->post('/api/import', [
            'file' => UploadedFile::fake()->createWithContent('customers.csv', $csv),
        ], [
            'Accept' => 'application/json',
        ]);
    }

    public function test_missing_name_is_reported(): void
    {
        $csv = <<<'CSV'
name,email,date_of_birth,annual_income
,missingname@example.com,1990-01-01,1000
CSV;

        $response = $this->postCsv($csv);

        $response->assertOk()->assertJson([
            'total_rows_processed' => 1,
            'imported_count' => 0,
            'failed_count' => 1,
        ]);

        $json = $response->json();
        $this->assertSame(2, $json['errors'][0]['row_number']);

        $fields = array_column($json['errors'][0]['errors'], 'field');
        $this->assertContains('name', $fields);
    }

    public function test_missing_email_is_reported(): void
    {
        $csv = <<<'CSV'
name,email,date_of_birth,annual_income
John Doe,,1990-01-01,1000
CSV;

        $response = $this->postCsv($csv);

        $response->assertOk()->assertJson([
            'total_rows_processed' => 1,
            'imported_count' => 0,
            'failed_count' => 1,
        ]);

        $json = $response->json();
        $fields = array_column($json['errors'][0]['errors'], 'field');
        $this->assertContains('email', $fields);
    }

    public function test_bad_email_format_is_reported(): void
    {
        $csv = <<<'CSV'
name,email,date_of_birth,annual_income
Bad Email,bad-email,1990-01-01,1000
CSV;

        $response = $this->postCsv($csv);

        $response->assertOk()->assertJson([
            'total_rows_processed' => 1,
            'imported_count' => 0,
            'failed_count' => 1,
        ]);

        $json = $response->json();
        $messages = array_column($json['errors'][0]['errors'], 'message');
        $this->assertTrue(in_array('Email must be a valid email address.', $messages, true));
    }

    public function test_future_date_of_birth_is_reported(): void
    {
        $csv = <<<'CSV'
name,email,date_of_birth,annual_income
Future DOB,future@example.com,2999-01-01,1000
CSV;

        $response = $this->postCsv($csv);

        $response->assertOk()->assertJson([
            'total_rows_processed' => 1,
            'imported_count' => 0,
            'failed_count' => 1,
        ]);

        $json = $response->json();
        $fields = array_column($json['errors'][0]['errors'], 'field');
        $this->assertContains('date_of_birth', $fields);
    }

    public function test_negative_annual_income_is_reported(): void
    {
        $csv = <<<'CSV'
name,email,date_of_birth,annual_income
Negative Income,neg@example.com,1990-01-01,-5
CSV;

        $response = $this->postCsv($csv);

        $response->assertOk()->assertJson([
            'total_rows_processed' => 1,
            'imported_count' => 0,
            'failed_count' => 1,
        ]);

        $json = $response->json();
        $fields = array_column($json['errors'][0]['errors'], 'field');
        $this->assertContains('annual_income', $fields);
    }

    public function test_optional_fields_blank_are_allowed(): void
    {
        $csv = <<<'CSV'
name,email,date_of_birth,annual_income
Optional Blank,blank@example.com,,
CSV;

        $response = $this->postCsv($csv);

        $response->assertOk()->assertJson([
            'total_rows_processed' => 1,
            'imported_count' => 1,
            'failed_count' => 0,
        ]);

        $this->assertDatabaseCount('customers', 1);
    }
}
