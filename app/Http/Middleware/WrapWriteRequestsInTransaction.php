<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class WrapWriteRequestsInTransaction
{
    public function handle(Request $request, Closure $next): Response
    {
        if (in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        // Retry transient deadlocks / serialization conflicts. This is especially
        // important when several users save documents and allocate serials together.
        return DB::transaction(fn() => $next($request), 5);
    }
}
