<?php

use App\Services\LeaveCalculationService;

it('calculates full-day, half-day, and multiple-day leave values', function () {
    $service = app(LeaveCalculationService::class);

    expect($service->calculateDays('full_day', '2026-07-10', '2026-07-10'))->toBe(1.0)
        ->and($service->calculateDays('half_day', '2026-07-10', '2026-07-10'))->toBe(0.5)
        ->and($service->calculateDays('multiple_day', '2026-07-06', '2026-07-08'))->toBe(3.0);
});
