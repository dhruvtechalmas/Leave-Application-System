<?php

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

it('allows the first registered user to access the admin dashboard when no super admin exists yet', function () {
    $this->seed(RolePermissionSeeder::class);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('admin.dashboard'));

    $response->assertOk();
});
