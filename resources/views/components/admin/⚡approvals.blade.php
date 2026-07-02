<?php

use App\Models\LeaveApplication;
use App\Services\LeaveApplicationService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

new class extends Component {
    public string $statusFilter = 'pending';

    public array $applications = [];

    public function mount(): void
    {
        $this->loadApplications();
    }

    public function loadApplications(): void
    {
        $this->applications = LeaveApplication::query()
            ->where('status', $this->statusFilter)
            ->with(['user', 'leaveType'])
            ->latest()
            ->get()
            ->toArray();
    }

    public function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->loadApplications();
    }

    public function approve(int $id): void
    {
        $application = LeaveApplication::query()->findOrFail($id);
        app(LeaveApplicationService::class)->approve($application);
        $this->statusFilter = 'approved';
        $this->loadApplications();
        session()->flash('success', 'Leave approved.');
    }

    public function reject(int $id): void
    {
        $application = LeaveApplication::query()->findOrFail($id);
        app(LeaveApplicationService::class)->reject($application, 'Rejected by admin');
        $this->statusFilter = 'rejected';
        $this->loadApplications();
        session()->flash('success', 'Leave rejected.');
    }

    public function delete(int $id): void
    {
        $application = LeaveApplication::findOrFail($id);

        // Allow deletion only for rejected requests
        if ($application->status !== 'rejected') {
            session()->flash('error', 'Only rejected leave requests can be deleted.');
            return;
        }

        $application->delete();

        $this->loadApplications();

        session()->flash('success', 'Rejected leave request deleted successfully.');
    }
};
?>

<div class="space-y-6">
    <div
        class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h1 class="text-xl font-semibold">Leave Approval Queue</h1>
            <a href="javascript:history.back()"
                class="rounded-md border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100">Back</a>
        </div>
        @if (session('success'))
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">
                {{ session('success') }}</div>
        @endif
        @error('approval')
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div>
        @enderror
        <div class="mt-4 border-b border-neutral-200">
            <div class="flex flex-wrap gap-2">
                @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
                    <button type="button" wire:click="setStatusFilter('{{ $key }}')"
                        class="rounded-t-md border border-b-0 px-4 py-2 text-sm {{ $statusFilter === $key ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-neutral-100 text-neutral-600' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Employee</th>
                        <th class="px-3 py-2 text-left font-semibold">Leave Type</th>
                        <th class="px-3 py-2 text-left font-semibold">Dates</th>
                        <th class="px-3 py-2 text-left font-semibold">Days</th>
                        <th class="px-3 py-2 text-left font-semibold">Document</th>
                        <th class="px-3 py-2 text-left font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse ($applications as $application)
                        <tr>
                            <td class="px-3 py-2">{{ $application['user']['name'] ?? 'User' }}</td>
                            <td class="px-3 py-2">{{ $application['leave_type']['name'] ?? 'Leave Type' }}</td>
                            <td class="px-3 py-2">{{ \Carbon\Carbon::parse($application['start_date'])->format('d M Y') }}
                                to {{ \Carbon\Carbon::parse($application['end_date'])->format('d M Y') }}</td>
                            <td class="px-3 py-2">{{ $application['days_requested'] }}</td>
                            <td class="px-3 py-2">
                                @if (!empty($application['attachment_path']))
                                    <a href="{{ Storage::url($application['attachment_path']) }}" target="_blank"
                                        class="text-blue-600">View</a>
                                @else
                                    <span class="text-neutral-500">None</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">
                                @if ($statusFilter === 'pending')
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="approve({{ $application['id'] }})"
                                            class="rounded-md bg-green-600 px-3 py-1 text-xs text-white">Approve</button>
                                        <button type="button" wire:click="reject({{ $application['id'] }})"
                                            class="rounded-md bg-red-600 px-3 py-1 text-xs text-white">Reject</button>
                                    </div>
                                @elseif ($statusFilter === 'approved')
                                    <button type="button" wire:click="reject({{ $application['id'] }})"
                                        class="rounded-md bg-red-600 px-3 py-1 text-xs text-white">Reject</button>
                                @elseif ($statusFilter === 'rejected')
                                    <div class="flex gap-2">
                                        <button type="button" wire:click="approve({{ $application['id'] }})"
                                            class="rounded-md bg-green-600 px-3 py-1 text-xs text-white">
                                            Approve
                                        </button>

                                        <button type="button" wire:click="delete({{ $application['id'] }})"
                                            wire:confirm="Are you sure you want to delete this rejected leave request?"
                                            class="rounded-md bg-red-600 px-3 py-1 text-xs text-white">
                                            Delete
                                        </button>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-4 text-sm text-gray-600">No {{ $statusFilter }} requests found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>