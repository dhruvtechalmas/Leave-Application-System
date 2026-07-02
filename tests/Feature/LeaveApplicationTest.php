<?php

use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveApplicationService;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

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
    expect((float) $balance->fresh()->days_available)->toBe(9.0)
        ->and((float) $balance->fresh()->days_used)->toBe(1.0);
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
    expect((float) $balance->fresh()->days_available)->toBe(4.0);
});

it('restores the balance when an approved personal leave request is rejected', function () {
    $user = User::factory()->create();
    $leaveType = LeaveType::create([
        'name' => 'Personal Leave',
        'code' => 'PL',
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
        'start_date' => '2026-07-15',
        'end_date' => '2026-07-15',
        'days_requested' => 1,
        'leave_mode' => 'full_day',
        'reason' => 'Personal appointment',
        'status' => 'pending',
    ]);

    $service = app(LeaveApplicationService::class);
    $service->approve($application);

    expect((float) $balance->fresh()->days_available)->toBe(9.0);

    $service->reject($application->fresh(), 'Request rejected');

    expect($application->fresh()->status)->toBe('rejected')
        ->and((float) $balance->fresh()->days_available)->toBe(10.0)
        ->and((float) $balance->fresh()->days_used)->toBe(0.0);
});

it('does not change the balance when a pending request is rejected', function () {
    $user = User::factory()->create();
    $leaveType = LeaveType::create([
        'name' => 'Personal Leave',
        'code' => 'PL',
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
        'start_date' => '2026-07-15',
        'end_date' => '2026-07-15',
        'days_requested' => 1,
        'leave_mode' => 'full_day',
        'reason' => 'Personal appointment',
        'status' => 'pending',
    ]);

    app(LeaveApplicationService::class)->reject($application);

    expect((float) $balance->fresh()->days_available)->toBe(10.0)
        ->and((float) $balance->fresh()->days_used)->toBe(0.0);
});

it('refuses approval when the employee has insufficient balance', function () {
    $user = User::factory()->create();
    $leaveType = LeaveType::create([
        'name' => 'Personal Leave',
        'code' => 'PL',
        'days_allowed' => 10,
        'requires_attachment' => false,
    ]);
    $balance = LeaveBalance::create([
        'user_id' => $user->id,
        'leave_type_id' => $leaveType->id,
        'days_available' => 0,
        'days_used' => 10,
    ]);
    $application = LeaveApplication::create([
        'user_id' => $user->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-07-15',
        'end_date' => '2026-07-15',
        'days_requested' => 1,
        'leave_mode' => 'full_day',
        'reason' => 'Personal appointment',
        'status' => 'pending',
    ]);

    expect(fn () => app(LeaveApplicationService::class)->approve($application))
        ->toThrow(ValidationException::class);

    expect($application->fresh()->status)->toBe('pending')
        ->and((float) $balance->fresh()->days_available)->toBe(0.0);
});

it('accepts leave dates in day month year format', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('leave-management')
        ->set('startDateInput', '15/07/2026')
        ->assertSet('startDateInput', '15/07/2026')
        ->assertSet('startDate', '2026-07-15');
});

it('does not submit personal leave when the balance is zero', function () {
    $user = User::factory()->create();
    $leaveType = LeaveType::create([
        'name' => 'Personal Leave',
        'code' => 'PL',
        'days_allowed' => 10,
        'requires_attachment' => false,
    ]);
    LeaveBalance::create([
        'user_id' => $user->id,
        'leave_type_id' => $leaveType->id,
        'days_available' => 0,
        'days_used' => 10,
    ]);

    Livewire::actingAs($user)
        ->test('leave-management')
        ->set('leaveTypeId', (string) $leaveType->id)
        ->set('startDateInput', '31/12/2030')
        ->set('reason', 'Personal appointment')
        ->call('submit')
        ->assertHasErrors(['leaveTypeId']);

    expect(LeaveApplication::query()->count())->toBe(0);
});
