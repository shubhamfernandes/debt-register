<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportDuplicateEmailInDatabaseTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    public function test_duplicate_email_against_existing_database_record_is_rejected(): void
    {
        // Existing DB record
        Customer::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'date_of_birth' => '1990-01-01',
            'annual_income' => 1000,
        ]);

        $csv = <<<CSV
name,email,date_of_birth,annual_income
New User,existing@example.com,1991-01-01,2000
CSV;

        $response = $this->post('/api/import', [
            'file' => UploadedFile::fake()->createWithContent('dup_db.csv', $csv),
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

        $this->assertSame(2, $json['errors'][0]['row_number']); // first data row
        $messages = array_column($json['errors'][0]['errors'], 'message');

        $this->assertTrue(
            in_array('Email already exists.', $messages, true),
            'Expected DB uniqueness error message.'
        );

        // Still only the original record
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_mixed_with_one_db_duplicate_and_one_new_valid_row_imports_only_valid(): void
    {
        Customer::create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'date_of_birth' => '1990-01-01',
            'annual_income' => 1000,
        ]);

        $csv = <<<CSV
name,email,date_of_birth,annual_income
Dup Row,existing@example.com,1991-01-01,2000
Valid Row,valid@example.com,1992-02-02,3000
CSV;

        $response = $this->post('/api/import', [
            'file' => UploadedFile::fake()->createWithContent('mixed_dup_db.csv', $csv),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJson([
                'total_rows_processed' => 2,
                'imported_count' => 1,
                'failed_count' => 1,
            ]);

        $this->assertDatabaseCount('customers', 2);
    }
}
