<?php

namespace App\Http\Controllers;

use Helpers;
use Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use App\Http\Models\Guest;
use App\Http\Models\Premium;
use App\Http\Models\TrxLog;
use Log;

class PremiumController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    public function iap(Request $request)
    {
        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = $credentials->game_id;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;
        $iap_trx_id = $credentials->iap_trx_id;
        $sku = $credentials->sku;
        $time = $credentials->time;
        $method = $credentials->method;
        $platform = $credentials->platform;
        $iap_trx_ref = $credentials->iap_trx_ref;
        $iap_currency = $credentials->iap_currency;
        $price = $credentials->price;
        $currency = $credentials->currency;
        $value = $credentials->value;
        $reference = $credentials->reference;
        $camp = $credentials->camp ?? "";
        $cid = $credentials->cid ?? "";

        //11=valid by validate client selanjutnya diolah oleh cron
        $status = $credentials->status ?? "";
        if($status==1){
            $status = 11;
        }

        $ip_client = $credentials->ip_client ?? "";
        $country = $credentials->country ?? "";
        $p = $credentials->p ?? "";
        $v = $credentials->v ?? "";
        $version = $p.' '.$v;
        $category = $credentials->category ?? "";
        
        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),$category.$currency.$value.$reference.$iap_trx_id.$iap_trx_ref.$sku.$time.$method.$platform,$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | premium/iap | -403 Wrong Signature!');
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        //check DebugCheat khusus live
        if($currency == 1){
            if($value > 2500 || $reference == 'cheat'){

                Log::channel('error')->error($guest_id.' | premium/iap | -403 Access Denied!.');
                return response()->json([
                    'code' => -403,
                    'message' => 'Access Denied!.',
                ]);
            }
        }

        //tolak jika value nya minus
        if($value < 0){

            Log::channel('error')->error($guest_id.' | premium/iap | -403 Access Denied!...');
            return response()->json([
                'code' => -403,
                'message' => 'Access Denied!...',
            ]);
        }

        //block user ngecheat di android
        if($platform == 'GooglePlay'){
            if(substr($iap_trx_id,0,4) != 'GPA.'){
                $guest = new Guest;
                $u = $guest::where('id','=',$guest_id)->first();
                $u->block = 1;
                $u->save();
            }
        }

        $log = new TrxLog;
        $checkLog = $log::where('iap_trx_id','=',$iap_trx_id)->first();
        if (isset($checkLog->id)) 
        {
            //jika iap trx exists
            Log::channel('error')->error($guest_id.' | premium/iap | -201 IAP Trx already exists!');
            return response()->json([
                'iap_trx_id' => $iap_trx_id,
                'game_id' => $game_id,
                'uid' => $uid,
                'guest_id' => $guest_id,
                'code' => -201,
                'message' => 'IAP Trx already exists!',
            ]);
        } 
        else 
        {
            //check if exists
            $u = Premium::where('game_id','=',$game_id)->where('guest_id','=',$guest_id)->first();
            if(isset($u->id)) 
            { 
                $u->premium = $u->premium + $value;
                if($platform == 'GooglePlay')
                {
                    if(substr($iap_trx_id,0,4) == 'GPA.')
                    {
                        if(!$u->save())
                        {
                            //jika error
                            Log::channel('error')->error($guest_id.' | premium/iap | -200 Access Currency UPDATE FAILED!');
                            return response()->json([
                                'game_id' => $game_id,
                                'guest_id' => $guest_id,
                                'code' => -200,
                                'message' => 'Currency UPDATE FAILED!',
                            ]); 
                        }
                        else{
                            //save log premium
                            //CommonController::log_premium('add',$game_id,$guest_id,$category,$currency,$value,$u->premium,$reference);
                        }
                    }
                }

                $p1 = $u->premium;
            } 
            else 
            {
                //add to log
                //CommonController::log_premium('add',$game_id,$guest_id,$category,$currency,$value,$value,$reference);        
                
                if($platform == 'GooglePlay')
                {
                    if(substr($iap_trx_id,0,4) == 'GPA.')
                    {
                        //save premium
                        $newCR = Premium::create([
                            'game_id' => $game_id,
                            'guest_id' => $guest_id,
                            'premium' => $value,
                            'premium2' => 0,
                            'status' => 1,
                            'created_at' => $credentials->now
                        ]);
                    }
                }
                else{
                    //save premium
                    $newCR = Premium::create([
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'premium' => $value,
                        'premium2' => 0,
                        'status' => 1,
                        'created_at' => $credentials->now
                    ]);
                }

                $p1 = $value;
            }

            //save log trx
            $log->game_id = $game_id;
            $log->guest_id = $guest_id;
            $log->method = $method;
            $log->platform = $platform;
            $log->time = $time;
            $log->iap_trx_id = $iap_trx_id;
            $log->iap_trx_ref = $iap_trx_ref;
            $log->iap_currency = $iap_currency;
            $log->price = $price;
            $log->currency = explode('_',$sku)[0];
            $log->value = $value;
            $log->sku = $sku;
            $log->reference = $version;
            $log->camp = $camp;
            $log->cid = $cid;
            $log->status = $status;
            $log->ip_client = $ip_client;
            $log->country = $country;

            if($log->save()) 
            {
                //create signature for client
                $varResponse = $category.$p1;
                $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

                //create payload for client
                $payload = array(
                    'category' => $category,
                    'result' => $value,
                    'game_id' => $game_id,
                    'guest_id' => $guest_id,
                    'signature' => $signatureResponse,
                    'x' => $credentials->x,
                    'code' => 200
                );

                $message = 'Trx Log CREATED Successfully!';

                //response
                return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
            } else {
                
                //jika gagal menyimpan log trx
                Log::channel('error')->error($guest_id.' | premium/iap | -200 Trx Log CREATED FAILED!');
                return response()->json([
                    'game_id' => $request->get('game_id'),
                    'guest_id' => $request->get('guest_id'),
                    'code' => -200,
                    'message' => 'Trx Log CREATED FAILED!',
                ]);
            }
        }
    }
}