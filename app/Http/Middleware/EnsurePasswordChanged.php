<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user?->must_change_password
            && ! $request->routeIs(
                'security.edit',
                'user-password.update',
                'password.confirm',
                'password.confirmation',
                'logout',
            )
        ) {
            return redirect()
                ->route('security.edit')
                ->with('toast', [
                    'type' => 'warning',
                    'message' => 'Sila tukar kata laluan sementara sebelum meneruskan.',
                ]);
        }

        return $next($request);
    }
}
