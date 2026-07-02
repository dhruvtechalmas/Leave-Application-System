<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();

        if (! $user) {
            abort(403);
        }

        $isFirstUser = User::query()->count() === 1;

        if ($user->hasRole('super_admin') || $isFirstUser) {
            return $next($request);
        }

        abort(403);
    }
}
