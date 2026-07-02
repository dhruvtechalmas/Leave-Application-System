<?php

use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\LeaveCalculationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public $leaveTypeId;

    public $leaveMode = 'full_day';

    public $startDate = null;

    public $endDate = null;

    public $startDateInput = '';

    public $endDateInput = '';

    public $daysRequested = 1;

    public $reason;

    public $attachment;

    public $leaveTypes = [];

    public $balances = [];

    public $applications = [];

    public $requiresAttachment = false;

    public $minimumNoticeDays = 0;

    public function mount(): void
    {
        $this->loadData();
    }

    public function updatedLeaveTypeId($value): void
    {
        $leaveType = LeaveType::find($value);

        $this->requiresAttachment = (bool) $leaveType?->requires_attachment;
        $this->minimumNoticeDays = (int) ($leaveType?->minimum_notice_days ?? 0);

        if (!$this->requiresAttachment) {
            $this->attachment = null;
        }

        // Clear previously selected dates
        $this->startDate = null;
        $this->endDate = null;
        $this->startDateInput = '';
        $this->endDateInput = '';

        $this->resetErrorBag();
    }

    public function updatedStartDateInput($value): void
    {
        // Clean and normalize the incoming date string
        $this->startDate = $this->normalizeDateInput($value);

        // Always keep the input bound value as standard Y-m-d format for HTML5 inputs
        $this->startDateInput = $this->startDate ?? '';

        $this->calculateDays();
    }

    public function updatedEndDateInput($value): void
    {
        $this->endDate = $this->normalizeDateInput($value);
        $this->endDateInput = $this->endDate ?? '';
        $this->calculateDays();
    }
    public function updatedLeaveMode($value): void
    {
        if ($value === 'full_day' || $value === 'half_day') {
            $this->endDate = $this->startDate;
            $this->endDateInput = $this->startDateInput;
        }

        $this->calculateDays();
    }

    public function calculateDays(): void
    {
        if ($this->leaveMode === 'full_day' || $this->leaveMode === 'half_day') {
            $this->daysRequested = $this->leaveMode === 'half_day' ? 0.5 : 1.0;
            $this->endDate = $this->startDate;
            $this->endDateInput = $this->startDateInput;

            return;
        }

        if ($this->startDate && $this->endDate) {
            $this->daysRequested = app(LeaveCalculationService::class)->calculateDays($this->leaveMode, $this->startDate, $this->endDate);
        }
    }

    private function normalizeDateInput($value): ?string
    {
        if (blank($value)) {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('d/m/Y', $value)
                ->format('Y-m-d');
        } catch (\Exception $e) {
        }

        try {
            return \Carbon\Carbon::parse($value)
                ->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function formatDateForInput(?string $value): string
    {
        if (blank($value)) {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($value)->format('d/m/Y');
        } catch (\Exception $exception) {
            return (string) $value;
        }
    }

    public function getMinDateProperty(): string
    {
        return now()->addDays($this->minimumNoticeDays)->toDateString();
    }

    // In your Livewire Component class
    public function getMinAllowedDateProperty(): string
    {
        return now()
            ->addDays($this->minimumNoticeDays)
            ->format('Y-m-d');
    }

    public function loadData(): void
    {
        $this->leaveTypes = LeaveType::query()->where('status', 'active')->get();
        $this->balances = LeaveBalance::query()->where('user_id', Auth::id())->with('leaveType')->get();
        $this->applications = LeaveApplication::query()->where('user_id', Auth::id())->latest()->get();
    }

    public function submit(): void
    {
        $this->calculateDays();

        $this->validate([
            'leaveTypeId' => ['required', 'exists:leave_types,id'],
            'leaveMode' => ['required', 'in:full_day,half_day,multiple_day'],
            'startDate' => ['required', 'date', 'after_or_equal:' . $this->getMinDateProperty()],
            'endDate' => ['nullable', 'date', 'after_or_equal:startDate'],
            'daysRequested' => ['required', 'numeric', 'min:0.5'],
            'reason' => ['required', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:2048'],
        ]);
        // Check leave balance BEFORE saving
        $balance = LeaveBalance::where('user_id', Auth::id())
            ->where('leave_type_id', $this->leaveTypeId)
            ->first();

        if (!$balance) {
            $this->addError('leaveTypeId', 'Leave balance not found.');
            return;
        }

        if ((float) $this->daysRequested > (float) $balance->days_available) {

            $this->addError(
                'daysRequested',
                "You only have {$balance->days_available} day(s) available."
            );

            return;
        }

        // Store attachment
        $attachmentPath = null;

        if ($this->requiresAttachment && $this->attachment) {
            $attachmentPath = $this->attachment->store('leave-attachments', 'public');
        }

        // NOW create the request
        LeaveApplication::create([
            'user_id' => Auth::id(),
            'leave_type_id' => $this->leaveTypeId,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'days_requested' => $this->daysRequested,
            'leave_mode' => $this->leaveMode,
            'reason' => $this->reason,
            'attachment_path' => $attachmentPath,
            'status' => 'pending',
        ]);

        $this->reset(['leaveTypeId', 'startDate', 'endDate', 'startDateInput', 'endDateInput', 'daysRequested', 'leaveMode', 'reason', 'attachment', 'requiresAttachment']);
        $this->leaveMode = 'full_day';
        $this->daysRequested = 1;
        $this->loadData();
        session()->flash('success', 'Leave request submitted successfully.');
    }
};
?>

<div class="space-y-6">
    <div
        class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold">Leave Management</h1>
                <p class="mt-2 text-sm text-gray-600">A simple beginner-friendly leave workflow with leave balances and
                    approval tracking.</p>
            </div>
            <a href="javascript:history.back()"
                class="rounded-md border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100">Back</a>
        </div>
    </div>

    @if (session('success'))
        <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
        <div
            class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <h2 class="text-lg font-semibold">Apply for Leave</h2>
            <form wire:submit.prevent="submit" class="mt-4 space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">Leave Type</label>
                    <select wire:model.live="leaveTypeId" class="w-full rounded-md border px-3 py-2">
                        <option value="">Select a type</option>
                        @foreach ($leaveTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Mode</label>
                    <select wire:model.live="leaveMode" class="w-full rounded-md border px-3 py-2">
                        <option value="full_day">Full Day</option>
                        <option value="half_day">Half Day</option>
                        <option value="multiple_day">Multiple Day</option>
                    </select>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Start Date</label>

                        <input type="date" wire:model.live="startDateInput"
                            min="{{ $this->minAllowedDate ?: $this->minAllowedDate}}"
                            class="w-full rounded-md border px-3 py-2">

                        <p class="mt-1 text-sm text-gray-500">
                            Minimum notice period: {{ $minimumNoticeDays }} day(s)
                        </p>
                    </div>
                    @if($leaveMode === 'multiple_day')
                        <div>
                            <label class="mb-1 block text-sm font-medium">End Date</label>

                            <input type="date" wire:model.live="endDateInput"
                                min="{{ $startDateInput ?: $this->minAllowedDate }}"
                                class="w-full rounded-md border px-3 py-2">
                        </div>
                    @endif

                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Days Requested</label>
                    <input type="number" step="0.5" wire:model="daysRequested"
                        class="w-full rounded-md border px-3 py-2" required>
                </div>
                @error('daysRequested')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                @if ($requiresAttachment)
                    <div wire:key="attachment-field-required">
                        <label class="mb-1 block text-sm font-medium">Attachment</label>
                        <input type="file" wire:model="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                            class="w-full rounded-md border px-3 py-2">
                        @error('attachment')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
                <div>
                    <label class="mb-1 block text-sm font-medium">Reason</label>
                    <textarea wire:model="reason" rows="3" class="w-full rounded-md border px-3 py-2"
                        required></textarea>
                </div>

                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-white">Submit Leave</button>
            </form>
        </div>

        <div class="space-y-6">
            <div
                class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">Leave Balances</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($balances as $balance)
                        <div class="rounded-lg border p-3">
                            <div class="font-medium">{{ $balance->leaveType->name }}</div>
                            <div class="text-sm text-gray-600">Available: {{ $balance->days_available }} days</div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-600">No leave balances yet.</p>
                    @endforelse
                </div>
            </div>

            <div
                class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
                <h2 class="text-lg font-semibold">Recent Requests</h2>
                <div class="mt-4 space-y-3">
                    @forelse ($applications as $application)
                        <div class="rounded-lg border p-3">
                            <div class="font-medium">{{ $application->leaveType->name }}</div>
                            <div class="text-sm text-gray-600">{{ $application->start_date->format('d M Y') }} to
                                {{ $application->end_date->format('d M Y') }} · {{ $application->status }}
                            </div>
                            @if ($application->attachment_path)
                                <a href="{{ Storage::url($application->attachment_path) }}" target="_blank"
                                    class="mt-2 inline-block text-sm font-medium text-blue-600">View attachment</a>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-600">No requests yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
<script>

    flatpickr('#start_date', {
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',

        onChange(selectedDates, dateStr) {
            $wire.set('startDateInput', dateStr);
        }
    });

    flatpickr('#end_date', {
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd/m/Y',

        onChange(selectedDates, dateStr) {
            $wire.set('endDateInput', dateStr);
        }
    });
</script>