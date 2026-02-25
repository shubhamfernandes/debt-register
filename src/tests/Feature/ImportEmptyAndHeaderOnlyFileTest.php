<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportEmptyAndHeaderOnlyFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_file_returns_422_before_row_processing(): void
    {
        $file = UploadedFile::fake()->createWithContent('empty.csv', '');

        $response = $this->post('/api/import', [
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);

        $response->assertJsonFragment([
            'error' => 'The uploaded file is empty.',
        ]);
    }

    public function test_header_only_file_returns_422_before_row_processing(): void
    {
        $csv = "name,email,date_of_birth,annual_income\n";
        $file = UploadedFile::fake()->createWithContent('header_only.csv', $csv);

        $response = $this->post('/api/import', [
            'file' => $file,
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);

        $response->assertJsonFragment([
            'error' => 'The CSV file contains only headers and no data.',
        ]);
    }
}
