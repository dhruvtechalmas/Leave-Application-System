<?php

use Database\Seeders\RolePermissionSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('creates the default super admin role and permissions', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(Role::findByName('super_admin'))->not->toBeNull();
    expect(Permission::findByName('manage users'))->not->toBeNull();
    expect(Permission::findByName('manage leave types'))->not->toBeNull();
});
