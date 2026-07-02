<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    protected $fillable = [
        'name',
        'code',
        'days_allowed',
        'requires_attachment',
        'description',
        'status',
    ];

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(LeaveApplication::class);
    }
}
