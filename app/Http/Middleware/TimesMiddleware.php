<?php

namespace App\Http\Middleware;

use Closure;

class TimesMiddleware
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
        $server_time = date("Y-m-d H");
        $client_time = date("Y-m-d H", $request->t);

        if($server_time != $client_time){
            return response()->json([
                'error' => 'Invalid TIME'
            ], 401);
        }

        return $next($request);
    }
}
