<?php

namespace App\Services;

use App\Models\ApprovalHistory;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use Illuminate\Support\Facades\Auth;

class LeaveApplicationService
{
    public function approve(LeaveApplication $application): LeaveApplication
    {
        if (! in_array($application->status, ['pending', 'rejected'], true)) {
            return $application;
        }

        $balance = LeaveBalance::query()
            ->where('user_id', $application->user_id)
            ->where('leave_type_id', $application->leave_type_id)
            ->first();

        if ($balance && in_array($application->status, ['pending', 'rejected'], true)) {
            $balance->days_available = max(0, $balance->days_available - $application->days_requested);
            $balance->days_used = $balance->days_used + $application->days_requested;
            $balance->save();
        }

        $application->status = 'approved';
        $application->approved_by = Auth::id();
        $application->approved_at = now()->toDateTimeString();
        $application->save();

        $application->histories()->create([
            'actor_id' => Auth::id(),
            'action' => 'approved',
            'comments' => 'Approved by admin',
        ]);

        return $application;
    }

    public function reject(LeaveApplication $application, ?string $comments = null): LeaveApplication
    {
        if ($application->status === 'rejected') {
            return $application;
        }

        $application->status = 'rejected';
        $application->approved_by = Auth::id();
        $application->approved_at = now()->toDateTimeString();
        $application->save();

        $application->histories()->create([
            'actor_id' => Auth::id(),
            'action' => 'rejected',
            'comments' => $comments,
        ]);

        return $application;
    }
}
