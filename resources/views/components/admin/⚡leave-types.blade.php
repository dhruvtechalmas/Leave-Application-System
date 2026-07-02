<?php

use App\Models\LeaveType;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $code = '';

    public string $minimumNoticeDays = '2';

    public bool $requiresAttachment = false;

    public string $description = '';

    public string $status = 'active';

    public ?int $editingId = null;

    public array $leaveTypes = [];

    public function mount(): void
    {
        $this->loadLeaveTypes();
    }

    public function loadLeaveTypes(): void
    {
        $this->leaveTypes = LeaveType::query()->latest()->get()->toArray();
    }

    public function saveLeaveType(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', $this->editingId ? 'unique:leave_types,code,'.$this->editingId : 'unique:leave_types,code'],
            'minimumNoticeDays' => ['required', 'numeric', 'min:0'],
        ]);

        $payload = [
            'name' => $this->name,
            'code' => strtoupper($this->code),
            'minimum_notice_days' => (int) $this->minimumNoticeDays,
            'requires_attachment' => $this->requiresAttachment,
            'description' => $this->description,
            'status' => $this->status,
        ];

        if ($this->editingId) {
            LeaveType::query()->findOrFail($this->editingId)->update($payload);
            session()->flash('success', 'Leave type updated.');
        } else {
            LeaveType::create($payload);
            session()->flash('success', 'Leave type created.');
        }

        $this->resetForm();
        $this->loadLeaveTypes();
    }

    public function editLeaveType(int $id): void
    {
        $leaveType = LeaveType::query()->findOrFail($id);
        $this->editingId = $leaveType->id;
        $this->name = $leaveType->name;
        $this->code = $leaveType->code;
        $this->minimumNoticeDays = (string) ($leaveType->minimum_notice_days ?? 2);
        $this->requiresAttachment = (bool) $leaveType->requires_attachment;
        $this->description = $leaveType->description ?? '';
        $this->status = $leaveType->status;
    }

    public function deleteLeaveType(int $id): void
    {
        LeaveType::query()->findOrFail($id)->delete();
        if ($this->editingId === $id) {
            $this->resetForm();
        }
        $this->loadLeaveTypes();
        session()->flash('success', 'Leave type deleted.');
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'code', 'minimumNoticeDays', 'requiresAttachment', 'description', 'status', 'editingId']);
        $this->minimumNoticeDays = '2';
        $this->requiresAttachment = false;
        $this->status = 'active';
    }
};
?>

<div class="space-y-6">
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h1 class="text-xl font-semibold">Leave Type Management</h1>
            <a href="javascript:history.back()" class="rounded-md border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100">Back</a>
        </div>
        @if (session('success'))
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif
        <form wire:submit.prevent="saveLeaveType" class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Leave Type Name</label>
                <input wire:model="name" class="w-full rounded-md border px-3 py-2" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Leave Type Code</label>
                <input wire:model="code" class="w-full rounded-md border px-3 py-2" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Minimum Notice Days</label>
                <input wire:model="minimumNoticeDays" type="number" class="w-full rounded-md border px-3 py-2" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Status</label>
                <select wire:model="status" class="w-full rounded-md border px-3 py-2">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="requiresAttachment" />
                    Documents required for this leave type
                </label>
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium">Description</label>
                <textarea wire:model="description" rows="3" class="w-full rounded-md border px-3 py-2"></textarea>
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-white">{{ $editingId ? 'Update Leave Type' : 'Create Leave Type' }}</button>
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="ml-2 rounded-md border px-4 py-2 text-sm">Cancel</button>
                @endif
            </div>
        </form>
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <h2 class="text-lg font-semibold">Existing Leave Types</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Name</th>
                        <th class="px-3 py-2 text-left font-semibold">Code</th>
                        <th class="px-3 py-2 text-left font-semibold">Docs</th>
                        <th class="px-3 py-2 text-left font-semibold">Status</th>
                        <th class="px-3 py-2 text-left font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($leaveTypes as $type)
                        <tr>
                            <td class="px-3 py-2">{{ $type['name'] }}</td>
                            <td class="px-3 py-2">{{ $type['code'] }}</td>
                            <td class="px-3 py-2">{{ $type['requires_attachment'] ? 'Required' : 'Optional' }}</td>
                            <td class="px-3 py-2">{{ $type['status'] }}</td>
                            <td class="px-3 py-2">
                                <div class="flex gap-2">
                                    <button type="button" wire:click="editLeaveType({{ $type['id'] }})" class="rounded-md border px-3 py-1 text-xs">Edit</button>
                                    <button type="button" wire:click="deleteLeaveType({{ $type['id'] }})" class="rounded-md border border-red-200 px-3 py-1 text-xs text-red-600">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>