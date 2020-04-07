<?php

namespace App\Http\Controllers;

use Helpers;
use Validator;

use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use App\Http\Models\Guest;
use App\Http\Models\Landmark;
use App\Http\Models\LandmarkLog;
use App\Http\Models\TrxLog;
use Log;

class LandmarkController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    
    public function list(Request $request)
    {
        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = $credentials->game_id;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;

        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),'',$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | landmark/list | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
                'message' => 'Wrong Signature!',
            ]);
        }

        //get data landmark
        $landmark = Landmark::select('id','landmark_sku')->whereIn('status',array(1,11))->where('game_id',$game_id)->where('guest_id',$guest_id)->get();

        //create signature for client
        $varResponse = "";
        $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

        //create payload for client
        $payload = array(
            'game_id' => $game_id,
            'guest_id' => $guest_id,
            'landmark' => json_encode($landmark),
            'signature' => $signatureResponse,
            'code' => 200,
            'x' => $credentials->x
        );

        $message = 'Get Data Landmark Success!';

        //response
        return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));

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
        //$value = $credentials->value;
        $value = 1;
        $reference = $credentials->reference;
        $camp = $credentials->camp ?? "";
        $cid = $credentials->cid ?? "";

        //11=valid by validate client khusus landmark
        $status = $credentials->status ?? "";
        if($status==1){
            $status = 11;
        }

        $ip_client = $credentials->ip_client ?? "";
        $country = $credentials->country ?? "";
        $p = $credentials->p ?? "";
        $v = $credentials->v ?? "";
        $version = $p.' '.$v;
        $category = $credentials->category;
        
        //cek signature

        $checkSignature = CommonController::checkSignature($signature,$request->segments(),$category.$currency.$reference.$iap_trx_id.$iap_trx_ref.$sku.$time.$method.$platform,$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | landmark/iap | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
                'message' => 'Wrong Signature!',
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

        //check if exists
        $u = Landmark::select('id')->where('game_id','=',$game_id)->where('guest_id','=',$guest_id)->where('landmark_sku','=',$sku)->whereIn('status',array(1,11))->first();
        if(!isset($u->id)) 
        { 
            //save log trx
            $TrxLog = new TrxLog;
            $checkLog = $TrxLog::select('id')->where('iap_trx_id','=',$iap_trx_id)->first();
            if(isset($checkLog->id)){

                Log::channel('error')->error($guest_id.' | landmark/iap | -201 IAP Trx already exists!');
                return response()->json([
                    'iap_trx_id' => $iap_trx_id,
                    'game_id' => $game_id,
                    'uid' => $uid,
                    'guest_id' => $guest_id,
                    'code' => -201,
                    'message' => 'IAP Trx already exists!',
                ]);
            }else{
                $TrxLog->game_id = $game_id;
                $TrxLog->guest_id = $guest_id;
                $TrxLog->method = $method;
                $TrxLog->platform = $platform;
                $TrxLog->time = $time;
                $TrxLog->iap_trx_id = $iap_trx_id;
                $TrxLog->iap_trx_ref = $iap_trx_ref;
                $TrxLog->iap_currency = $iap_currency;
                $TrxLog->price = $price;
                $TrxLog->currency = explode('_',$sku)[0];
                $TrxLog->value = $value;
                $TrxLog->sku = $sku;
                $TrxLog->reference = $version;
                $TrxLog->camp = $camp;
                $TrxLog->cid = $cid;
                $TrxLog->status = $status;
                $TrxLog->ip_client = $ip_client;
                $TrxLog->country = $country;
                $saveTrxLog = $TrxLog->save();

                if($saveTrxLog){

                    //add to log
                    $log = new LandmarkLog();
                    $log->game_id = $game_id;
                    $log->guest_id = $guest_id;
                    $log->category = $category;
                    $log->landmark_sku = $sku;
                    $log->reference = 'api-add:'.$reference;
                    $log->status = 1;
                    $log->created_at = date('Y-m-d H:i:s');
                    $log->updated_at = date('Y-m-d H:i:s');
                    $save_log = $log->save();

                    //save landmark
                    $save = Landmark::create([
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'landmark_sku' => $sku,
                        'status' => $status,
                    ]);

                    $varResponse = $category;
                    $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

                    //create payload for client
                    $payload = array(
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'signature' => $signatureResponse,
                        'x' => $credentials->x,
                        'code' => 200
                    );

                    $message = 'Trx Log CREATED Successfully!';
                    return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
                }
            }
        }
        else{
            
            //jika landmark sudah ada sebelumnya
            Log::channel('error')->error($guest_id.' | landmark/iap | -201 Landmark already exists!');
            return response()->json([
                'game_id' => $game_id,
                'uid' => $uid,
                'guest_id' => $guest_id,
                'code' => -201,
                'message' => 'Landmark already exists!',
            ]);
        }
    }
}