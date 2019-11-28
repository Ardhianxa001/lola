<?php

namespace App\Http\Controllers;

use DB;
use Helpers;
use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\CommonController;
use Carbon;
use App\Http\Models\Gamedata;

class GamedataController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $dt = Carbon\Carbon::now();
        $now = $dt->toDateTimeString();
        $this->now = $dt->format('Y-m-d H:i:s');
    }

    public function index(Request $request)
    {
        $game_id = 2;
        $request->q = strtolower($request->q);
        $data = Gamedata::select('data')->where('category','gamedata')->where('game_id',$game_id)->where('name',$request->q)->first();
        if(!isset($data->data)){
            return response()->json([
                'code' => -401,
                'message' => 'Invalid Request!',
            ]);
        }
        else{
            return response()->json([
                'code' => 200,
                'data' => $data->data
            ]);
        }
    }
}
