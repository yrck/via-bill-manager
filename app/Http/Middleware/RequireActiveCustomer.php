<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->fresh()?->is_active, 403, 'This account is inactive. Contact VIA support.');

        return $next($request);
    }
}
