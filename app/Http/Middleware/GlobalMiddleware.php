<?php

namespace App\Http\Middleware;

use Closure;

class GlobalMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // return response()->json([
        //     'code' => -503,
        //     'message' => 'Service Unavailable'
        // ], 201);

        return $next($request);
    }
}
