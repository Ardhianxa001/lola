<?php

namespace App\Http\Middleware;

use Closure;
use App\Http\Models\Guest;
use App\Http\Models\FlagLog;

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
            if($request->p=="" || $request->v=="" || $request->t==""){
                $FlagLog = new FlagLog();
                $FlagLog->game_id = 2;
                $FlagLog->guest_id = $guest_id;
                $FlagLog->code = 2;
                $FlagLog->reason = 'Invalid Request For PVT';
                $FlagLog->status = 1;
                $FlagLog->timing = date('Y-m-d H:i:s');
                $FlagLog->save();
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

        if($request->t > 0){
            $client_time = $request->t;
        }

        if($client_time < $date_min_value || $client_time > $date_max_value){

            $FlagLog = new FlagLog();
            $FlagLog->game_id = 2;
            $FlagLog->guest_id = $guest_id;
            $FlagLog->code = 3;
            $FlagLog->reason = 'Invalid TIME';
            $FlagLog->status = 1;
            $FlagLog->timing = date('Y-m-d H:i:s',$client_time);
            $FlagLog->save();
        }

        if(substr($credentials->uid,0,32)=='c37853590e2c42cf960d6ba706f93b66'){
            return response()->json([
                'code' => -503,
                'message' => 'Service Unavailable'
            ], 201);
        }

        return $next($request);
    }
}
