<?php

namespace App\Services;

use Carbon\Carbon;

class LeaveCalculationService
{
    public function calculateDays(string $mode, string $startDate, ?string $endDate = null): float
    {
        if ($mode === 'half_day') {
            return 0.5;
        }

        if ($mode === 'full_day') {
            return 1.0;
        }

        if ($mode === 'multiple_day' && $startDate && $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);

            return max(1, $start->diffInDays($end) + 1);
        }

        return 0.0;
    }
}
