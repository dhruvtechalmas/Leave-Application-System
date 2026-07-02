<?php

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use Livewire\Component;

new class extends Component
{
    public string $userId = '';

    public string $leaveTypeId = '';

    public string $daysAvailable = '';

    public string $remarks = '';

    public ?int $editingId = null;

    public array $balances = [];

    public array $users = [];

    public array $leaveTypes = [];

    public function mount(): void
    {
        $this->users = User::query()->latest()->get()->toArray();
        $this->leaveTypes = LeaveType::query()->latest()->get()->toArray();
        $this->loadBalances();
    }

    public function loadBalances(): void
    {
        $this->balances = LeaveBalance::query()->with(['user', 'leaveType'])->latest()->get()->toArray();
    }

    public function saveBalance(): void
    {
        $this->validate([
            'userId' => ['required', 'exists:users,id'],
            'leaveTypeId' => ['required', 'exists:leave_types,id'],
            'daysAvailable' => ['required', 'numeric', 'min:0'],
        ]);

        if ($this->editingId) {
            LeaveBalance::query()->findOrFail($this->editingId)->update([
                'user_id' => $this->userId,
                'leave_type_id' => $this->leaveTypeId,
                'days_available' => $this->daysAvailable,
                'days_used' => 0,
            ]);
            session()->flash('success', 'Leave balance updated.');
        } else {
            LeaveBalance::create([
                'user_id' => $this->userId,
                'leave_type_id' => $this->leaveTypeId,
                'days_available' => $this->daysAvailable,
                'days_used' => 0,
            ]);
            session()->flash('success', 'Leave balance saved.');
        }

        $this->resetForm();
        $this->loadBalances();
    }

    public function editBalance(int $id): void
    {
        $balance = LeaveBalance::query()->findOrFail($id);
        $this->editingId = $balance->id;
        $this->userId = (string) $balance->user_id;
        $this->leaveTypeId = (string) $balance->leave_type_id;
        $this->daysAvailable = (string) $balance->days_available;
        $this->remarks = '';
    }

    public function deleteBalance(int $id): void
    {
        LeaveBalance::query()->findOrFail($id)->delete();
        if ($this->editingId === $id) {
            $this->resetForm();
        }
        $this->loadBalances();
        session()->flash('success', 'Leave balance deleted.');
    }

    public function resetForm(): void
    {
        $this->reset(['userId', 'leaveTypeId', 'daysAvailable', 'remarks', 'editingId']);
        $this->daysAvailable = '';
    }
};
?>

<div class="space-y-6">
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h1 class="text-xl font-semibold">Leave Balance Management</h1>
            <a href="javascript:history.back()" class="rounded-md border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100">Back</a>
        </div>
        @if (session('success'))
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif
        <form wire:submit.prevent="saveBalance" class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">User</label>
                <select wire:model="userId" class="w-full rounded-md border px-3 py-2">
                    <option value="">Select user</option>
                    @foreach ($users as $user)
                        <option value="{{ $user['id'] }}">{{ $user['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Leave Type</label>
                <select wire:model="leaveTypeId" class="w-full rounded-md border px-3 py-2">
                    <option value="">Select leave type</option>
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type['id'] }}">{{ $type['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Balance Days</label>
                <input wire:model="daysAvailable" type="number" step="0.5" class="w-full rounded-md border px-3 py-2" placeholder="Enter balance days" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Remarks</label>
                <input wire:model="remarks" class="w-full rounded-md border px-3 py-2" />
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-white">{{ $editingId ? 'Update Balance' : 'Save Balance' }}</button>
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="ml-2 rounded-md border px-4 py-2 text-sm">Cancel</button>
                @endif
            </div>
        </form>
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <h2 class="text-lg font-semibold">Current Balances</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Employee</th>
                        <th class="px-3 py-2 text-left font-semibold">Leave Type</th>
                        <th class="px-3 py-2 text-left font-semibold">Available</th>
                        <th class="px-3 py-2 text-left font-semibold">Allowed Days</th>
                        <th class="px-3 py-2 text-left font-semibold">Used</th>
                        <th class="px-3 py-2 text-left font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($balances as $balance)
                        <tr>
                            <td class="px-3 py-2">{{ $balance['user']['name'] ?? 'User' }}</td>
                            <td class="px-3 py-2">{{ $balance['leave_type']['name'] ?? 'Type' }}</td>
                            <td class="px-3 py-2">{{ $balance['days_available'] }} days</td>
                            <td class="px-3 py-2">{{ $balance['leave_type']['days_allowed'] ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $balance['days_used'] }} days</td>
                            <td class="px-3 py-2">
                                <div class="flex gap-2">
                                    <button type="button" wire:click="editBalance({{ $balance['id'] }})" class="rounded-md border px-3 py-1 text-xs">Edit</button>
                                    <button type="button" wire:click="deleteBalance({{ $balance['id'] }})" class="rounded-md border border-red-200 px-3 py-1 text-xs text-red-600">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>