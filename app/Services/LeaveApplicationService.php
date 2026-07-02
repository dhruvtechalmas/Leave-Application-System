<?php

namespace App\Services;

use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveApplicationService
{
    public function approve(LeaveApplication $application): LeaveApplication
    {
        return DB::transaction(function () use ($application): LeaveApplication {
            $lockedApplication = LeaveApplication::query()->lockForUpdate()->findOrFail($application->id);

            if (! in_array($lockedApplication->status, ['pending', 'rejected'], true)) {
                return $lockedApplication;
            }

            $balance = $this->lockedBalanceFor($lockedApplication);

            if (! $balance || (float) $balance->days_available < (float) $lockedApplication->days_requested) {
                throw ValidationException::withMessages([
                    'approval' => 'This request cannot be approved because the employee has insufficient leave balance.',
                ]);
            }

            $balance->days_available = (float) $balance->days_available - (float) $lockedApplication->days_requested;
            $balance->days_used = (float) $balance->days_used + (float) $lockedApplication->days_requested;
            $balance->save();

            $lockedApplication->status = 'approved';
            $lockedApplication->approved_by = Auth::id();
            $lockedApplication->approved_at = now();
            $lockedApplication->save();

            $lockedApplication->histories()->create([
                'actor_id' => Auth::id(),
                'action' => 'approved',
                'comments' => 'Approved by admin',
            ]);

            return $lockedApplication;
        });
    }

    public function reject(LeaveApplication $application, ?string $comments = null): LeaveApplication
    {
        return DB::transaction(function () use ($application, $comments): LeaveApplication {
            $lockedApplication = LeaveApplication::query()->lockForUpdate()->findOrFail($application->id);

            if ($lockedApplication->status === 'rejected') {
                return $lockedApplication;
            }

            if ($lockedApplication->status === 'approved') {
                $balance = $this->lockedBalanceFor($lockedApplication);

                if (! $balance) {
                    throw ValidationException::withMessages([
                        'approval' => 'This approved request cannot be rejected because its leave balance record is missing.',
                    ]);
                }

                $balance->days_available = (float) $balance->days_available + (float) $lockedApplication->days_requested;
                $balance->days_used = max(0, (float) $balance->days_used - (float) $lockedApplication->days_requested);
                $balance->save();
            }

            $lockedApplication->status = 'rejected';
            $lockedApplication->approved_by = Auth::id();
            $lockedApplication->approved_at = now();
            $lockedApplication->save();

            $lockedApplication->histories()->create([
                'actor_id' => Auth::id(),
                'action' => 'rejected',
                'comments' => $comments,
            ]);

            return $lockedApplication;
        });
    }

    private function lockedBalanceFor(LeaveApplication $application): ?LeaveBalance
    {
        return LeaveBalance::query()
            ->where('user_id', $application->user_id)
            ->where('leave_type_id', $application->leave_type_id)
            ->lockForUpdate()
            ->first();
    }
}
