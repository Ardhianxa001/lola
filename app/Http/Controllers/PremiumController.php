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
use App\Http\Models\TrxLog;
use Carbon;

class PremiumController extends Controller
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

    public function iap(Request $request)
    {
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
        $status = $credentials->status ?? "";
        $ip_client = $credentials->ip_client ?? "";
        $country = $credentials->country ?? "";
        $p = $credentials->p ?? "";
        $v = $credentials->v ?? "";
        $version = $p.' '.$v;
        
        $category = $credentials->category;
        

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = $category.$currency.$value.$reference.$iap_trx_id.$iap_trx_ref.$sku.$time.$method.$platform;
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        //check DebugCheat khusus live
        if($currency == 1){
            if($value > 2500 || $reference == 'cheat'){
                return response()->json([
                    'code' => -403,
                    'message' => 'Access Denied!.',
                ]);
            }
        } elseif ($currency == 2) {
            if($reference == 'cheat'){
                return response()->json([
                    'code' => -403,
                    'message' => 'Access Denied!..',
                ]);
            }
        }

        //tolak jika value nya minus
        if($value < 0){
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

        //check if exists
        $cCheck = new Premium;
        
        $u = $cCheck::where('game_id','=',$game_id)
            ->where('guest_id','=',$guest_id)
            ->first();

        if (isset($u->id)) 
        { 
            if ($currency == '1') {
                $premium = $u->premium + $value;
                $result = $premium;
            } else if ($currency == '2') {
                $premium2 = $u->premium2 + $value;
                $result = $premium2;
            }

            CommonController::log_premium('add',$game_id,$guest_id,$category,$currency,$value,$result,$reference);       
            
            if (isset($premium)) $u->premium = $premium;
            if (isset($premium2)) $u->premium2 = $premium2;

            if(!$category) $category = '';
            if(!$u->premium) $u->premium = 0;
            if(!$u->premium2) $u->premium2 = 0;

            $c = $category;
            $p1 = $u->premium;
            $p2 = $u->premium2;
            
            if (!$u->save()) {
                return response()->json([
                    'game_id' => $game_id,
                    'guest_id' => $guest_id,
                    'code' => -200,
                    'message' => 'Currency UPDATE FAILED!',
                ]); 
            }
        } else {
            //if not
            $dt = Carbon\Carbon::now();
            $now = $dt->toDateTimeString();
            $now = $dt->format('Y-m-d H:i:s');
            
            if ($currency == '1') {
                $premium = $value;
                $premium2 = 0;
                $result = $premium;
            } else if ($currency == '2') {
                $premium = 0;
                $premium2 = $value;
                $result = $premium2;
            }
            //add to log
            CommonController::log_premium('add',$game_id,$guest_id,$category,$currency,$value,$result,$reference);        
            
            $newCR = Premium::create([
                'game_id' => $game_id,
                'guest_id' => $guest_id,
                'premium' => $premium,
                'premium2' => $premium2,
                'status' => 1,
                'created_at' => $now
            ]);

            if(!$category) $category = '';
            if(!$premium) $premium = 0;
            if(!$premium2) $premium2 = 0;

            $c = $category;
            $p1 = $premium;
        }

        $log = new TrxLog;

        $checkLog = $log::where('iap_trx_id','=',$iap_trx_id)
            ->first();
        if (isset($checkLog->id)) {
            return response()->json([
                'iap_trx_id' => $iap_trx_id,
                'game_id' => $game_id,
                'uid' => $uid,
                'guest_id' => $guest_id,
                'code' => -201,
                'message' => 'IAP Trx already exists!',
            ]);
        } else {
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
            
            if ($log->save()) {
                $r = 1;
            }

            if ($r) {
                //return getError::status(100);

                $varResponse = $c.$p1;
                $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

                $payload = array(
                    'category' => $c,
                    'result' => $result,
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
                //return getError::status(-100);
                return response()->json([
                    'game_id' => $request->get('game_id'),
                    'guest_id' => $request->get('guest_id'),
                    'code' => -200,
                    'message' => 'Trx Log CREATED FAILED!',
                ]);
            }
        }
    }

    public function dec(Request $request)
    {
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
        $delta = $credentials->delta;
        $reference = $credentials->reference;

        $category = $credentials->category;
        $currency = $credentials->currency;
        

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = $reference.$delta;
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        $category_data = array('booster','lootbox','iap','shop','reward','event','redeem','reset','revive','gift','daily','continue','life');
        if(!in_array($category, $category_data)){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Category!',
            ]);
        }

        $error = 0;
        if(!is_int($delta)){
            $error++;
        }

        if($delta < 0){
            $error++;
        }

        //update premium
        $find = Premium::select('id','premium')->where('guest_id',$guest_id)->where('game_id',$game_id)->where('status',1)->first();

        if(!isset($find->id)){
            $error++;
        }

        if($find->premium < $delta){
            $error++;
        }
        
        if($error > 0){
            return response()->json([
                'code' => -401,
                'message' => 'user does not have diamond!',
            ]);
        }
        else{
            $removePremium = Premium::find($find->id);
            $removePremium->premium = $find->premium - $delta;
            $update = $removePremium->save();
            $result = $find->premium - $delta;
            
            if($update){
                //save log
                $log = CommonController::log_premium('dec',$game_id,$guest_id,$category,$currency,$delta,$result,$reference);
            }

            $varResponse = $result;
            $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

            $payload = array(
                'guest_id' => $guest_id,
                'uid' => $uid,
                'game_id' => $game_id,
                'signature' => $signatureResponse,
                'premium' => $result,
                'code' => 200,
                'x' => $credentials->x
            );

            $message = 'Decrease Diamond Successfully!';

            return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
        }
    }

    public function add(Request $request)
    {
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
        $delta = $credentials->delta;
        $reference = $credentials->reference;
        $category = $credentials->category;
        $currency = $credentials->currency;
        

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = $reference.$delta;
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        $category_data = array('iap','shop','reward','event','redeem','reset','revive','gift','daily','continue','life');
        if(!in_array($category, $category_data)){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Category!',
            ]);
        }

        $error = 0;
        if(!is_int($delta)){
            $error++;
        }

        if($delta < 0){
            $error++;
        }

        if($error==0)
        {
            //update premium
            $find = Premium::select('id','premium')->where('guest_id',$guest_id)->where('game_id',$game_id)->where('status',1)->first();

            if(isset($find->id)){
                
                $addPremium = Premium::find($find->id);
                $addPremium->premium = $find->premium + $delta;
                $update = $addPremium->save();

                $result = $find->premium + $delta;
            }
            else{
                $addPremium = new Premium();
                $addPremium->game_id = $game_id;
                $addPremium->guest_id = $guest_id;
                $addPremium->premium = $delta;
                $addPremium->status = 1;
                $update = $addPremium->save();

                $result = $delta;
            }

            $varResponse = $result;
            $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

            $payload = array(
                'guest_id' => $guest_id,
                'uid' => $uid,
                'game_id' => $game_id,
                'signature' => $signatureResponse,
                'premium' => $result,
                'code' => 200,
                'x' => $credentials->x
            );

            $message = 'Add Diamond Successfully!';

            return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
        }
        else
        {
            return response()->json([
                'code' => -401,
                'message' => 'Error Premium Value',
            ]);
        }
    }

    public function get(Request $request)
    {
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

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = '';
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        $getData = Premium::select('premium')->where('guest_id',$guest_id)->where('status',1)->where('game_id',$game_id)->first();

        $premium = 0;
        if(isset($getData->premium)){
            $premium = $getData->premium;
        }

        $varResponse = $premium;
        $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

        $payload = array(
            'guest_id' => $guest_id,
            'uid' => $uid,
            'game_id' => $game_id,
            'signature' => $signatureResponse,
            'premium' => $premium,
            'code' => 200,
            'x' => $credentials->x
        );

        $message = 'Get Data Successfully!';

        return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
    }
}
