<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name',
        'email',
        'date_of_birth',
        'annual_income',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'annual_income' => 'decimal:2',
    ];

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('id');
    }
}
