<?php

namespace App\Http\Controllers;

use DB;
use Helpers;
use Validator;
use App\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Firebase\JWT\ExpiredException;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\CommonController;
use App\Http\Models\Guest;
use App\Http\Models\Profile;
use App\Http\Models\Data;
use App\Http\Models\Premium;
use App\Http\Models\Currency;
use App\Http\Models\Redeem;
use App\Http\Models\RedeemLog;
use App\Http\Models\TrxLog;
use Carbon;

class RedeemController extends Controller
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

    public function get(Request $request){
        $validator = Validator::make($request->all(), [
            'txt' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => -401,
                'message' => 'Invalid Request!',
            ]);
        }

        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = $credentials->game_id;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;

        $code = $credentials->code;
        $platform = $request->get('p');
        $reference = 'redeem';

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = $code;
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);
        //dd($checkSignature);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -401,
                'message' => 'Wrong Signature!',
            ]);
        }

        $rCheck = new Redeem;

        $redeem = $rCheck::where('code', $code)->where('status',1)->first();

        if(isset($redeem->id)){
            $gCheck = new Guest;

            $block = $gCheck::select('block')->where('id', $guest_id)->where('origin_game_id',$game_id)->first();
            if($block->block == 1){
                return response()->json([
                    'game_id' => $game_id,
                    'guest_id' => $guest_id,
                    'code' => -403,
                    'message' => "You've blocked for this game",
                ]); 
            }
            
            if($game_id != $redeem->game_id){
                return response()->json([
                    'game_id' => $game_id,
                    'guest_id' => $guest_id,
                    'code' => -201,
                    'message' => $code.' not for this game!',
                ]); 

                // return Response::json([
                //     'hello' => $value
                // ], 201);
            }

            if($redeem->expired == 1){
                if(strtotime($redeem->end_date) <= strtotime($this->now)){
                    return response()->json([
                        'code' => -202,
                        'message' => $code.' expired!'
                    ]);
                }

                if(strtotime($this->now) <= strtotime($redeem->start_date)){
                    return response()->json([
                        'code' => -205,
                        'message' => $code.' can only be redeemed between '.date("Y-m-d", strtotime($redeem->start_date)).' to '.date("Y-m-d", strtotime($redeem->end_date))
                    ]);
                }
            }

            $rlCheck = new RedeemLog;

            $redeem_log = $rlCheck::where('guest_id', $guest_id)->where('redeem_id', $redeem->id)->where('game_id',$game_id)->first();

            if(isset($redeem_log->id)){
                return response()->json([
                    'code' => -201,
                    'message' => 'Already Redeemed!'
                ]);
            }

            if($redeem->unlimited == 0){
                if($redeem->last_position_qty < 1){
                    return response()->json([
                        'code' => -206,
                        'message' => $code.' is out of stock!'
                    ]);
                }
            }

            if($redeem->unlimited == 0){
                $redeem->last_position_qty = $redeem->last_position_qty - 1;
                $redeem->save();
            }

            //dapet diamond
            $pCheck = new Premium;

            if($redeem->premium != 0){
                $cCheck_premium = $pCheck::where('game_id','=',$game_id)
                    ->where('guest_id','=',$guest_id)
                    ->first();



                if(!isset($cCheck_premium)){
                    $cCheck_premium = Premium::create([
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'premium' => 0,
                        'premium2' => 0,
                        'status' => 1,
                        'created_at' => $this->now
                    ]);
                }

                $cCheck_premium = Premium::find($guest_id);
                $cCheck_premium->premium = $cCheck_premium->premium + $redeem->premium;
                if (!$cCheck_premium->save()){
                    return response()->json([
                        'code' => -200,
                        'message' => 'Premium UPDATE FAILED!',
                    ]);
                }
                CommonController::log_premium('add',$game_id,$guest_id,'redeem',1,$redeem->premium,$cCheck_premium->premium,$reference); 

            }

            if($redeem->coin != 0){
                $cCheck = new Currency;

                $cCheck_currency = $cCheck::where('guest_id', $guest_id)
                            ->where('game_id', $game_id)
                            ->first();

                if(!isset($cCheck_currency)){
                    $cCheck_currency = Currency::create([
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'coin' => 0,
                        'seasonal' => 0,
                        'status' => 1,
                        'created_at' => $this->now
                    ]);
                }

                $cCheck_currency = Currency::find($guest_id);
                $cCheck_currency->coin = $cCheck_currency->coin + $redeem->coin;
                $cCheck_currency->save();
                if (!$cCheck_currency->save()){
                    return response()->json([
                        'code' => -200,
                        'message' => 'Premium UPDATE FAILED!',
                    ]);
                }
                CommonController::log_currency('add',$game_id,$guest_id,'redeem',$redeem->coin,$cCheck_currency->coin,$reference);
            }

            //ITEM DISINI
            $reward_type = 0;
            $reward_value = 0;
            if($redeem->reward_type > 0)
            {
                $reward_type = $redeem->reward_type;
                $reward_value = $redeem->reward_value;
            }

            //create log redeem
            $logRedeem = array(
                'game_id' => $game_id,
                'guest_id' => $guest_id,
                'redeem_id' => $redeem->id,
                'platform' => $platform,
                'hit' => 1,
                'created_at' => $this->now
            );

            CommonController::log_redeem($logRedeem);

            $varResponse = $redeem->image.$redeem->text.$redeem->premium;
            $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

            $payload = array(
                'game_id' => $game_id,
                'guest_id' => $guest_id,
                'redeem_code' => $redeem->code,
                'image' => $redeem->image,
                'text' => $redeem->text,
                'premium' => $redeem->premium,
                'item_id' => $reward_type,
                'item_value' => $reward_value,
                //'premium2' => $redeem->premium2,
                'ticket' => $redeem->coin,
                'signature' => $signatureResponse,
                'code' => 200,
                'x' => $credentials->x
            );

            $message = 'Redeem Success!';

            //response
            return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
        } else {
            return response()->json([
                'code' => -203,
                'message' => $code.' invalid/wrong!'
            ]);
        }
    }
}
