<?php

namespace App\Support;

final class CustomerRowValidation
{
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'annual_income' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    public static function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'email.required' => 'Email is required.',
            'email.email' => 'Email must be a valid email address.',
            'email.unique' => 'Email already exists.',
            'date_of_birth.date' => 'Date of birth must be a valid date.',
            'date_of_birth.before_or_equal' => 'Date of birth must not be in the future.',
            'annual_income.numeric' => 'Annual income must be a number.',
            'annual_income.gt' => 'Annual income must be a positive number.',
        ];
    }
}
