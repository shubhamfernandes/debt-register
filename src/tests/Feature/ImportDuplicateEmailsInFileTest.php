<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportDuplicateEmailsInFileTest extends TestCase
{
    use RefreshDatabase;
    /**
     * A basic feature test example.
     */
    public function test_duplicate_emails_within_same_file_are_flagged_for_both_rows(): void
    {
        $csv = <<<CSV
name,email,date_of_birth,annual_income
Dupe One,dupe@example.com,1990-01-01,2000
Dupe Two,dupe@example.com,1991-01-01,3000
CSV;

        $response = $this->postJson('/api/import', [
            'file' => UploadedFile::fake()->createWithContent('dupes.csv', $csv),
        ]);

        $response->assertOk()
            ->assertJson([
                'total_rows_processed' => 2,
                'imported_count' => 0,
                'failed_count' => 2,
            ]);

        $json = $response->json();

        // both rows (2 and 3) should appear as errors
        $rowNumbers = array_column($json['errors'], 'row_number');
        $this->assertContains(2, $rowNumbers);
        $this->assertContains(3, $rowNumbers);

        // Each error row should include an email duplicate message
        foreach ($json['errors'] as $errRow) {
            $messages = array_column($errRow['errors'], 'message');
            $this->assertTrue(
                in_array('Duplicate email found within the same file.', $messages, true),
                'Expected duplicate email message on each duplicate row.'
            );
        }

        $this->assertDatabaseCount('customers', 0);
    }

    public function test_duplicate_emails_in_file_do_not_block_other_valid_rows(): void
    {
        $csv = <<<CSV
name,email,date_of_birth,annual_income
Dupe One,dupe@example.com,1990-01-01,2000
Valid User,valid@example.com,1990-01-01,1000
Dupe Two,dupe@example.com,1991-01-01,3000
CSV;

        $response = $this->postJson('/api/import', [
            'file' => UploadedFile::fake()->createWithContent('dupes_with_valid.csv', $csv),
        ]);

        $response->assertOk()
            ->assertJson([
                'total_rows_processed' => 3,
                'imported_count' => 1,
                'failed_count' => 2,
            ]);

        $this->assertDatabaseCount('customers', 1);
    }
}
