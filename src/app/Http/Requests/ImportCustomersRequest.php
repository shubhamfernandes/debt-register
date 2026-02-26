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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:5120'], // 5MB limit and only csv and sometimes txt files can have the same data format as csv
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please upload a CSV file.',
            'file.file' => 'The uploaded file must be a valid file.',
            'file.mimes' => 'The uploaded file must be a valid CSV file',
            'file.max' => 'The CSV file must not exceed 5MB.',
        ];
    }
}
