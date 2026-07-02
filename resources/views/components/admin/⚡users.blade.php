<?php

use App\Models\User;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'employee';

    public ?int $editingId = null;

    public array $users = [];

    public function mount(): void
    {
        $this->loadUsers();
    }

    public function loadUsers(): void
    {
        $this->users = User::query()->with('roles')->latest()->get()->toArray();
    }

    public function saveUser(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', $this->editingId ? 'unique:users,email,'.$this->editingId : 'unique:users,email'],
        ];

        if (! $this->editingId) {
            $rules['password'] = ['required', 'min:6'];
        }

        $this->validate($rules);

        if ($this->editingId) {
            $user = User::query()->findOrFail($this->editingId);
            $payload = [
                'name' => $this->name,
                'email' => $this->email,
            ];

            if ($this->password !== '') {
                $payload['password'] = $this->password;
            }

            $user->update($payload);
            $user->syncRoles([$this->role]);
            session()->flash('success', 'User updated.');
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
            ]);
            $user->assignRole($this->role);
            session()->flash('success', 'User created.');
        }

        $this->resetForm();
        $this->loadUsers();
    }

    public function editUser(int $id): void
    {
        $user = User::query()->with('roles')->findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->password = '';
        $this->role = $user->roles->first()?->name ?? 'employee';
    }

    public function deleteUser(int $id): void
    {
        $user = User::query()->findOrFail($id);
        if (auth()->id() === $user->id) {
            session()->flash('success', 'You cannot delete your own account.');

            return;
        }

        $user->delete();
        if ($this->editingId === $id) {
            $this->resetForm();
        }
        $this->loadUsers();
        session()->flash('success', 'User deleted.');
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'email', 'password', 'role', 'editingId']);
        $this->role = 'employee';
    }
};
?>

<div class="space-y-6">
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h1 class="text-xl font-semibold">User Management</h1>
            <a href="javascript:history.back()" class="rounded-md border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-100">Back</a>
        </div>
        @if (session('success'))
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif
        <form wire:submit.prevent="saveUser" class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Name</label>
                <input wire:model="name" class="w-full rounded-md border px-3 py-2" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Email</label>
                <input wire:model="email" type="email" class="w-full rounded-md border px-3 py-2" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Password</label>
                <input wire:model="password" type="password" class="w-full rounded-md border px-3 py-2" />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Role</label>
                <select wire:model="role" class="w-full rounded-md border px-3 py-2">
                    <option value="employee">Employee</option>
                    <option value="super_admin">Super Admin</option>
                </select>
            </div>
            <div class="md:col-span-2">
                <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-white">{{ $editingId ? 'Update User' : 'Create User' }}</button>
                @if ($editingId)
                    <button type="button" wire:click="resetForm" class="ml-2 rounded-md border px-4 py-2 text-sm">Cancel</button>
                @endif
            </div>
        </form>
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-neutral-900">
        <h2 class="text-lg font-semibold">Existing Users</h2>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full divide-y divide-neutral-200 text-sm">
                <thead>
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold">Name</th>
                        <th class="px-3 py-2 text-left font-semibold">Email</th>
                        <th class="px-3 py-2 text-left font-semibold">Role</th>
                        <th class="px-3 py-2 text-left font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @foreach ($users as $user)
                        <tr>
                            <td class="px-3 py-2">{{ $user['name'] }}</td>
                            <td class="px-3 py-2">{{ $user['email'] }}</td>
                            <td class="px-3 py-2">{{ $user['roles'][0]['name'] ?? 'employee' }}</td>
                            <td class="px-3 py-2">
                                <div class="flex gap-2">
                                    <button type="button" wire:click="editUser({{ $user['id'] }})" class="rounded-md border px-3 py-1 text-xs">Edit</button>
                                    <button type="button" wire:click="deleteUser({{ $user['id'] }})" class="rounded-md border border-red-200 px-3 py-1 text-xs text-red-600">Delete</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>