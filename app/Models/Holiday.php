<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $fillable = [
        'name',
        'holiday_date',
        'recurring',
    ];

    protected $casts = [
        'holiday_date' => 'date',
        'recurring' => 'boolean',
    ];
}
