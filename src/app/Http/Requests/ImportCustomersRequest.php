<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCustomersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please upload a CSV file.',
            'file.file' => 'The uploaded file must be a valid file.',
            'file.extensions' => 'The uploaded file must be a CSV file.',
            'file.max' => 'The CSV file must not exceed 5MB.',
        ];
    }
}
