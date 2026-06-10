<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }

        $userRole = $request->user()->role;

        foreach ($roles as $role) {
            if ($userRole === Role::from($role)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to access this page.');
    }
}
