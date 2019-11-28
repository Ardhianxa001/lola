<?php

namespace App\Http\Middleware;

use Closure;
use App\Http\Models\Guest;

class PvtMiddleware
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
        $credentials = $request->get('claim');
        $guest_id = $credentials->guest_id;

        if(!isset($_GET['p']) || !isset($_GET['v']) || !isset($_GET['t'])){
            if($_SERVER['HTTP_USER_AGENT'] != "PostmanRuntime/7.18.1"){
                $block = Guest::find($guest_id);
                $block->block = 2;
                $block->save();
            }
        }
        else{
            if($request->p=="" || $request->v=="" || $request->t==""){
                $block = Guest::find($guest_id);
                $block->block = 2;
                $block->save();
            }
        }

        $now = date('Y-m-d H:i:s');

        $date_min = date_create($now);
        $date_max = date_create($now);

        date_add($date_min, date_interval_create_from_date_string('-30 minutes'));
        $date_min_value = strtotime(date_format($date_min, 'Y-m-d H:i:s'));

        date_add($date_max, date_interval_create_from_date_string('+30 minutes'));
        $date_max_value = strtotime(date_format($date_max, 'Y-m-d H:i:s'));

        $client_time = 0;
        if(isset($_GET['t'])){
            $client_time = $_GET['t'];
        }

        if($client_time < $date_min_value || $client_time > $date_max_value){
            $block = Guest::find($guest_id);
            $block->block = 3;
            $block->save();
        }

        return $next($request);
    }
}
