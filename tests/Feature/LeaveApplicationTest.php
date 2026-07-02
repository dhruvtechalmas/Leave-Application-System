<?php

use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveApplicationService;

it('deducts the balance when a leave request is approved', function () {
    $user = User::factory()->create();

    $leaveType = LeaveType::create([
        'name' => 'Casual Leave',
        'code' => 'CL',
        'days_allowed' => 10,
        'requires_attachment' => false,
    ]);

    $balance = LeaveBalance::create([
        'user_id' => $user->id,
        'leave_type_id' => $leaveType->id,
        'days_available' => 10,
        'days_used' => 0,
    ]);

    $application = LeaveApplication::create([
        'user_id' => $user->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-10',
        'days_requested' => 1,
        'leave_mode' => 'full_day',
        'reason' => 'Medical appointment',
        'status' => 'pending',
    ]);

    app(LeaveApplicationService::class)->approve($application);

    expect($application->fresh()->status)->toBe('approved');
    expect($balance->fresh()->days_available)->toBe(9);
});

it('allows a rejected leave request to be approved again', function () {
    $user = User::factory()->create();

    $leaveType = LeaveType::create([
        'name' => 'Sick Leave',
        'code' => 'SL',
        'days_allowed' => 5,
        'requires_attachment' => false,
    ]);

    $balance = LeaveBalance::create([
        'user_id' => $user->id,
        'leave_type_id' => $leaveType->id,
        'days_available' => 5,
        'days_used' => 0,
    ]);

    $application = LeaveApplication::create([
        'user_id' => $user->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-07-12',
        'end_date' => '2026-07-12',
        'days_requested' => 1,
        'leave_mode' => 'full_day',
        'reason' => 'Fever',
        'status' => 'pending',
    ]);

    app(LeaveApplicationService::class)->reject($application, 'Needs more context');
    app(LeaveApplicationService::class)->approve($application);

    expect($application->fresh()->status)->toBe('approved');
    expect($balance->fresh()->days_available)->toBe(4);
});
