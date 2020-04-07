<?php

namespace App\Http\Controllers;

use Helpers;
use Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use App\Http\Models\Guest;
use App\Http\Models\Premium;
use App\Http\Models\Currency;
use App\Http\Models\Redeem;
use App\Http\Models\RedeemLog;
use Log;

class RedeemController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    public function get(Request $request){

        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = $credentials->game_id;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;
        $code = $credentials->code;
        $platform = $credentials->p;
        $reference = 'redeem';

        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),$code,$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){

            Log::channel('error')->error($guest_id.' | redeem/get |  -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
                'message' => 'Wrong Signature!',
            ]);
        }

        //get data redeem
        $rCheck = new Redeem;
        $redeem = $rCheck::where('code', $code)->where('status',1)->first();

        if(isset($redeem->id)){

            $gCheck = new Guest;
            $block = $gCheck::select('block')->where('id', $guest_id)->where('origin_game_id',$game_id)->first();
            if($block->block == 1){

                //jika user ter block
                Log::channel('error')->error($guest_id." | redeem/get | -403 You've blocked for this game");
                return response()->json([
                    'game_id' => $game_id,
                    'guest_id' => $guest_id,
                    'code' => -403,
                    'message' => "You've blocked for this game",
                ]); 
            }
            
            if($game_id != $redeem->game_id){

                //jika redeem code bukan untuk game lola
                Log::channel('error')->error($guest_id.' | redeem/get | -201 '. $code.' not for this game!');
                return response()->json([
                    'game_id' => $game_id,
                    'guest_id' => $guest_id,
                    'code' => -201,
                    'message' => $code.' not for this game!',
                ]); 
            }

            if($redeem->expired == 1){

                //jika redeem code sudah expired
                if(strtotime($redeem->end_date) <= strtotime($credentials->now)){
                    Log::channel('error')->error($guest_id.' | redeem/get | -202 '. $code.' expired');
                    return response()->json([
                        'code' => -202,
                        'message' => $code.' expired!'
                    ]);
                }

                //jika redeem code belum waktunya di tebus
                if(strtotime($credentials->now) <= strtotime($redeem->start_date)){
                    Log::channel('error')->error($guest_id.' | redeem/get | -205 '. $code.' can only be redeemed between '.date("Y-m-d", strtotime($redeem->start_date)).' to '.date("Y-m-d", strtotime($redeem->end_date)));
                    return response()->json([
                        'code' => -205,
                        'message' => $code.' can only be redeemed between '.date("Y-m-d", strtotime($redeem->start_date)).' to '.date("Y-m-d", strtotime($redeem->end_date))
                    ]);
                }
            }

            //get data redeem log
            $rlCheck = new RedeemLog;
            $redeem_log = $rlCheck::where('guest_id', $guest_id)->where('redeem_id', $redeem->id)->where('game_id',$game_id)->first();

            if(isset($redeem_log->id)){

                //jika sudah pernah redeem
                Log::channel('error')->error($guest_id.' | redeem/get | -201 Already Redeemed!');
                return response()->json([
                    'code' => -201,
                    'message' => 'Already Redeemed!'
                ]);
            }

            if($redeem->unlimited == 0){
                if($redeem->last_position_qty < 1){

                    //jika stock habis
                    Log::channel('error')->error($guest_id.' | redeem/get | -206 is out of stock!');
                    return response()->json([
                        'code' => -206,
                        'message' => $code.' is out of stock!'
                    ]);
                }
            }

            if($redeem->unlimited == 0){

                //kurangi stock item jika redeem tidak unlimited
                $redeem->last_position_qty = $redeem->last_position_qty - 1;
                $redeem->save();
            }

            //dapet diamond
            $pCheck = new Premium;
            if($redeem->premium != 0){
                $cCheck_premium = $pCheck::where('game_id','=',$game_id)
                    ->where('guest_id','=',$guest_id)
                    ->first();


                //save premium
                if(!isset($cCheck_premium)){
                    $cCheck_premium = Premium::create([
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'premium' => 0,
                        'premium2' => 0,
                        'status' => 1,
                        'created_at' => $credentials->now
                    ]);
                }

                //update premium error
                $cCheck_premium->premium = $cCheck_premium->premium + $redeem->premium;
                if (!$cCheck_premium->save()){

                    Log::channel('error')->error($guest_id.' | redeem/get | -200 Premium UPDATE FAILED!');
                    return response()->json([
                        'code' => -200,
                        'message' => 'Premium UPDATE FAILED!',
                    ]);
                }

                //save log premium
                //CommonController::log_premium('add',$game_id,$guest_id,'redeem',1,$redeem->premium,$cCheck_premium->premium,$reference); 

            }


            //dapet ticket
            if($redeem->coin != 0){
                //get data coin
                $cCheck = new Currency;
                $cCheck_currency = $cCheck::where('guest_id', $guest_id)->where('game_id', $game_id)->first();

                //save ticket
                if(!isset($cCheck_currency)){
                    $cCheck_currency = Currency::create([
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'coin' => 0,
                        'seasonal' => 0,
                        'status' => 1,
                        'created_at' => $credentials->now
                    ]);
                }

                //save ticket
                $cCheck_currency->coin = $cCheck_currency->coin + $redeem->coin;
                if (!$cCheck_currency->save()){

                    Log::channel('error')->error($guest_id.' | redeem/get | -200 Ticket UPDATE FAILED!');
                    return response()->json([
                        'code' => -200,
                        'message' => 'Ticket UPDATE FAILED!',
                    ]);
                }
                //CommonController::log_currency('add',$game_id,$guest_id,'redeem',$redeem->coin,$cCheck_currency->coin,$reference);
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
                'created_at' => $credentials->now
            );

            //save log redeem
            CommonController::log_redeem($logRedeem);

            //create signature for client
            $varResponse = $redeem->image.$redeem->text.$redeem->premium;
            $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

            //create payload for client
            $payload = array(
                'game_id' => $game_id,
                'guest_id' => $guest_id,
                'redeem_code' => $redeem->code,
                'image' => $redeem->image,
                'text' => $redeem->text,
                'premium' => $redeem->premium,
                'item_id' => $reward_type,
                'item_value' => $reward_value,
                'ticket' => $redeem->coin,
                'signature' => $signatureResponse,
                'code' => 200,
                'x' => $credentials->x
            );

            $message = 'Redeem Success!';

            //response
            return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
        } else {
            Log::channel('error')->error($guest_id.' | redeem/get | -203 '. $code.' invalid/wrong!');
            return response()->json([
                'code' => -203,
                'message' => $code.' invalid/wrong!'
            ]);
        }
    }
}