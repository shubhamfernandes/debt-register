<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportHeaderNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_is_case_and_whitespace_tolerant(): void
    {
        // Header has different casing + extra spaces
        $csv = <<<CSV
 Name , Email , Date_Of_Birth , Annual_Income
John Doe,john@example.com,1990-05-12,50000
CSV;

        $response = $this->postJson('/api/import', [
            'file' => UploadedFile::fake()->createWithContent('header_norm.csv', $csv),
        ]);

        $response->assertOk()->assertJson([
            'total_rows_processed' => 1,
            'imported_count' => 1,
            'failed_count' => 0,
            'aborted' => false,
            'fatal_error' => null,
        ]);

        $this->assertDatabaseCount('customers', 1);
    }

    public function test_invalid_header_still_returns_422_with_validation_style_shape(): void
    {
        $csv = <<<CSV
name,email,dob,annual_income
John Doe,john@example.com,1990-05-12,50000
CSV;

        $response = $this->post('/api/import', [
            'file' => UploadedFile::fake()->createWithContent('bad_header.csv', $csv),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);

        $response->assertJsonFragment([
            'message' => 'The given data was invalid.',
        ]);

        $response->assertJsonPath('errors.file.0', 'Invalid CSV header. Expected: name,email,date_of_birth,annual_income');
    }
}
