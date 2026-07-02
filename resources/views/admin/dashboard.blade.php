<x-layouts::app :title="__('Admin Dashboard')">
    <div class="space-y-6">
        <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
            <h1 class="text-2xl font-semibold">Super Admin Dashboard</h1>
            <p class="mt-2 text-sm text-gray-600">Manage users, leave types, balances, and approvals from one place.</p>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('admin.users') }}" class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-gray-500">Users</div>
                <div class="mt-2 text-xl font-semibold">Manage Users</div>
            </a>
            <a href="{{ route('admin.leave-types') }}" class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-gray-500">Leave Types</div>
                <div class="mt-2 text-xl font-semibold">Create & Edit</div>
            </a>
            <a href="{{ route('admin.leave-balances') }}" class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-gray-500">Balances</div>
                <div class="mt-2 text-xl font-semibold">Adjust Balances</div>
            </a>
            <a href="{{ route('admin.approvals') }}" class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                <div class="text-sm text-gray-500">Approvals</div>
                <div class="mt-2 text-xl font-semibold">Approve or Reject</div>
            </a>
        </div>
    </div>
</x-layouts::app>
