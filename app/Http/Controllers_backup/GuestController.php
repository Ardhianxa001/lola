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
use App\Http\Models\Inbox;
use App\Http\Models\Premium;
use App\Http\Models\PremiumLog;
use App\Http\Models\Currency;
use App\Http\Models\CurrencyLog;
use App\Http\Models\Redeem;
use App\Http\Models\Event;
use App\Http\Models\RedeemLog;
use App\Http\Models\Games;
use App\Http\Models\Mongo;

use Carbon;
require_once(config('filesystems.geoip.phar'));
use GeoIp2\Database\Reader;

class GuestController extends Controller
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

        $this->p = "";
        $this->v = "";
        $this->t = "";
        if(isset($_GET['p'])){$this->p = $_GET['p'];}
        if(isset($_GET['v'])){$this->v = $_GET['v'];}
        if(isset($_GET['t'])){$this->t = $_GET['t'];}
    }

    public function mongodb()
    {
        $cars = Mongo::get();

        print_r($cars);

        exit;
    }

    public function daily_login(Request $request)
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

        $request_code = $credentials->code;
        $time = $credentials->time;

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }



        $varRequest = $request_code.$time;
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);
        $data = Data::select('request_code','request_time')->where('game_id',$game_id)->where('guest_id',$guest_id)->first();

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!'

            ]); 
        }

        if($request_code=="" && $data->request_time != 0){
            return response()->json([
                'code' => -403,
                'message' => 'Invalid Request',
            ]);
        }

        //$request_code=="" && 

        if($request_code=="" && $data->request_time==0){
            $code = 200;
            $message = "Success";
        }
        else{
            if($data->request_code==$request_code && date('Y-m-d',$data->request_time)==date('Y-m-d',$time)){
                $code = -403;
                $message = "Invalid Request";
            }
            elseif($time <= $data->request_time){
                $code = -403;
                $message = "Invalid Request";
            }
            else{
                $code = 200;
                $message = "Success";
            }
        }
        

        if($code==200){
            $new_request_code = md5(date('Y-m-d H:i:s'));

            $updateData = Data::find($guest_id);
            $updateData->request_code = $new_request_code;
            $updateData->request_time = $time;
            $updateData->save();

            $varResponse = '';
            $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

            $payload = array(
                'game_id' => $game_id,
                'guest_id' => $guest_id,
                'code' => $code,
                'request_code' => $new_request_code,
                'signature' => $signatureResponse,
                'x' => $request->x
            );

            

            //response
            return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
        }
        else{
            return response()->json([
                'code' => $code,
                'message' => $message,
            ]);
        }
    }

    public function inbox_claim(Request $request)
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
        $inbox_id = $credentials->inbox_id;
        $reference = $credentials->reference;

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = $inbox_id;
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        $redeem = Redeem::
        select('reward_type','reward_value','inbox.type','redeem.id','inbox.game_id','redeem_id','premium','coin','expired','inbox.start_date','inbox.end_date','inbox.type','unlimited','qty','last_position_qty','inbox.text')
        ->join('inbox','inbox.redeem_id','=','redeem.id')
        ->where('inbox.id', $inbox_id)->where('redeem.status',1)->where('redeem.game_id',$game_id)->where('inbox.game_id',$game_id)
        ->first();

        //lanjut disini

        if(isset($redeem->redeem_id)){
            $gCheck = new Guest;

            $block = $gCheck::select('block')->where('id', $guest_id)->first();
            if($block->block == 1){
                return response()->json([
                    'game_id' => $game_id,
                    'guest_id' => $guest_id,
                    'code' => -200,
                    'message' => "You've blocked for this game",
                ]); 
            }
            
            if($game_id != $redeem->game_id){
                return response()->json([
                    'game_id' => $game_id,
                    'guest_id' => $guest_id,
                    'code' => -200,
                    'message' => 'Claim not for this game!',
                ]); 
            }

            if(strtotime($redeem->end_date) <= strtotime($this->now)){
                    return response()->json([
                        'code' => -202,
                        'message' => 'Expired!',
                    ]);
            }

            // if($redeem->expired == 1){
            //     if(strtotime($redeem->end_date) <= strtotime($this->now)){
            //         return response()->json([
            //             'code' => -202,
            //             'message' => 'Expired!',
            //         ]);
            //     }

            //     if(strtotime($this->now) <= strtotime($redeem->start_date)){
            //         return response()->json([
            //             'code' => -205,
            //             'message' => 'can only be redeemed between '.date("Y-m-d", strtotime($redeem->start_date)).' to '.date("Y-m-d", strtotime($redeem->end_date))
            //         ]);
            //     }
            // }



            $rlCheck = new RedeemLog;

            $redeem_log = $rlCheck::where('guest_id', $guest_id)->where('redeem_id', $redeem->id)->where('game_id',$game_id)->first();

            if(isset($redeem_log->id)){
                return response()->json([
                    'code' => -201,
                    'message' => 'Already Redeemed!',
                ]);
            }

            if($redeem->unlimited == 0){
                if($redeem->last_position_qty < 1){
                    return response()->json([
                        'code' => -206,
                        'message' => 'out of stock!',
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

                $jumlah_premium = $cCheck_premium->premium + $redeem->premium;
                $result_premium = $jumlah_premium;
                $category = "";

                if($redeem->type==4){
                    $category = "event";
                }
                elseif($redeem->type==5){
                    //kompensasi
                    $category = "reward";
                }
                elseif($redeem->type==3){
                    //sponsor
                    $category = "reward";
                }

                CommonController::log_premium('add',$game_id,$guest_id,$category,1,$redeem->premium,$result_premium,$reference); 

                $cCheck_premium->premium = $jumlah_premium;

                if (!$cCheck_premium->save()) {
                    return response()->json([
                        'code' => -200,
                        'message' => 'Premium UPDATE FAILED!',
                    ]);
                }
            }

            if($redeem->premium2 != 0){
                $cCheck_premium2 = $pCheck::where('game_id','=',$game_id)
                    ->where('guest_id','=',$guest_id)
                    ->first();

                if(!isset($cCheck_premium2)){
                    $cCheck_premium2 = Premium::create([
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'premium' => 0,
                        'premium2' => 0,
                        'status' => 1,
                        'created_at' => $this->now
                    ]);
                }

                $category = "";
                if($redeem->type==4){
                    $category = "event";
                }
                elseif($redeem->type==5){
                    //kompensasi
                    $category = "reward";
                }
                elseif($redeem->type==3){
                    //sponsor
                    $category = "reward";
                }
                
                $jumlah_premium2 = $cCheck_premium2->premium2 + $redeem->premium2;
                $result_premium2 = $jumlah_premium2;

                CommonController::log_premium('add',$game_id,$guest_id,$category,2,$redeem->premium2,$result_premium2,$reference);

                $cCheck_premium2->premium2 = $jumlah_premium2;

                if (!$cCheck_premium2->save()) {
                    return response()->json([
                        'code' => -200,
                        'message' => 'Premium UPDATE FAILED!',
                    ]);
                }
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

                $category = "";
                if($redeem->type==4){
                    $category = "event";
                }
                elseif($redeem->type==5){
                    //kompensasi
                    $category = "reward";
                }
                elseif($redeem->type==3){
                    //sponsor
                    $category = "reward";
                }

                $cCheck_currency->coin = $cCheck_currency->coin + $redeem->coin;
                $cCheck_currency->save();

                CommonController::log_currency('add',$game_id,$guest_id,$category,$redeem->coin,$cCheck_currency->coin,$reference);
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
                'platform' => $this->p,
                'hit' => 1,
                'created_at' => $this->now
            );

            CommonController::log_redeem($logRedeem);

            // $updateStatusInbox = Inbox::find($inbox_id);
            // $updateStatusInbox->status = 2;
            // $updateStatusInbox->save();

            $varResponse = $redeem->code.$redeem->image.$redeem->text.$redeem->premium;

            
            $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

            $payload = array(
                'game_id' => $game_id,
                'guest_id' => $guest_id,
                'redeem_code' => $redeem->code,
                'image' => $redeem->image,
                'text' => $redeem->text,
                'premium' => $redeem->premium,
                'ticket' => $redeem->coin,
                'signature' => $signatureResponse,
                'code' => 200,
                'item_id' => $reward_type,
                'item_value' => $reward_value,
                'x' => $credentials->x
            );

            $message = 'Redeem Success!';

            //response
            return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
        } else {

            return response()->json([
                'code' => -203,
                'message' => 'Invalid Inbox ID',
            ]);
        }
    }

    public function inbox(Request $request)
    {
        //require_once(config('filesystems.geoip.phar'));
        // echo storage_path().'/geoip/geoip2.phar';
        // exit;
        $validator = Validator::make($request->all(), [
            'txt' => 'required'
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

        $profile = new Profile;
        $checkProfile = $profile::select('country')->where('guest_id','=',$guest_id)->first();
        $country = $checkProfile->country;
        if($country == "" || $country == NULL){ // if country null detect geoip
            //geoip
            if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
                $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
            }
            $reader = new Reader(config('filesystems.geoip.database'));
            $record = $reader->city($_SERVER['REMOTE_ADDR']);
            $country = $record->country->isoCode;
        }

        if($country != "ID" && $country != "KR" && $country != "US"){
            $country = "US";
        }

        $varRequest = '';
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        $guest = new Guest;
        
        $u = $guest::where('uid','=',$uid)
            ->where('origin_game_id','=',$game_id)
            ->first();

        if (isset($u->id)) { //if exists

            $now = date('Y-m-d H:i:s');

            //coin adalah ticket
            $inbox = DB::connection('mysql3')->select(DB::raw(
            "
                SELECT i.*, r.image, r.text, r.premium, r.coin FROM inbox i 
                join redeem r on r.id=i.redeem_id 
                WHERE 
                (
                    i.guest_id='$guest_id' 
                    and i.game_id='$game_id' 
                    and r.game_id='$game_id' 
                    and i.start_date <= '$now' 
                    and i.end_date >= '$now'
                    and r.status=1 
                    and i.status=1
                ) 
                or 
                (
                    (i.guest_id=0 and (r.country='$country' or r.country='all') 
                    and (i.platform='$credentials->p' or i.platform='all')) 
                    and i.game_id='$game_id' 
                    and r.game_id='$game_id' 
                    and i.start_date <= '$now' 
                    and i.end_date >= '$now' 
                    and r.status=1 
                    and i.status=1
                )
            "));

            $inbox = Self::convertJsonToObject($inbox, array('id','type','image','text','premium','id','end_date','status','guest_id','redeem_id','game_id'));


            $varResponse = '';
            $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

            $payload = array(
                'inbox' => $inbox,
                'guest_id' => $u->id,
                'game_id' => $game_id,
                'uid' => $uid,
                'status' => '1',
                'signature' => $signatureResponse,
                'x' => $credentials->x
            );

            $message = 'Get Inbox Data Success';

            //response
            return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
        } else {
            return response()->json([
                'code' => -100,
                'message' => 'Guest NOT FOUND',
            ]);
        }
    }

    public function convertJsonToObject($object, $field) 
    {
        $result = array();
        foreach ($object as $key => $val) {
            $data   = new \stdClass();

            //Field
            foreach ($field as $val_field) {
                $data->$val_field = $val->$val_field;
                if($val->type==4){
                    $event = Event::select('text','image','sponsor')->where('redeem_id',$val->redeem_id)->where('status',1)->first();
                    if(isset($event->id)){
                        $data->event_text = $event->text;
                        $data->event_image = $event->image;
                        $data->event_sponsor = $event->sponsor;
                    }
                }

                //cek log redeem
                $log_redeem = RedeemLog::select('id')->where('guest_id', $val->guest_id)->where('redeem_id', $val->redeem_id)->where('game_id',$val->game_id)->first();
                if(isset($log_redeem->id)){
                    $data->status = 2;
                }
                else{
                    $data->status = 1;
                }
            }
            
            $result[] = $data;
        }
        return $result;
    }

    public function refresh(Request $request) //refresh token
    {
         $validator = Validator::make($request->all(), [
            'txt' => 'required'
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
                'message' => 'Access Denied!',
            ]);
        }

        $varResponse = '';
        $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

        $guest = new Guest;
        
        $u = $guest::where('uid','=',$uid)
            ->where('id','=',$guest_id)
            ->first();
        if (isset($u->id)) {
            //check if token expire
            $dt = Carbon\Carbon::now();
            $now = $dt->toDateTimeString();
            $now = $dt->format('Y-m-d H:i:s');

            //if (strtotime($u->token_expire) <= strtotime($now)) {
                $token = CommonController::generateToken();
                $dt2 = Carbon\Carbon::now()->addMonths(3);
                $token_expire = $dt2->toDateTimeString();
                $token_expire = $dt2->format('Y-m-d H:i:s');

                $u->token = $token;
                $u->token_expire = $token_expire;
                if ($u->save()) {

                    $payload = array(
                        'guest_id' => $u->id,
                        'uid' => $uid,
                        'token' => $token,
                        'token_expire' => $token_expire,
                        'signature' => $signatureResponse,
                        'x' => $credentials->x
                    );

                    $message = 'Token REFRESHED!';

                    //response
                    return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
                } else {
                    return response()->json([
                        'guest_id' => $u->id,
                        'uid' => $uid,
                        'code' => -200,
                        'message' => 'Token Refresh FAILED!',
                    ]);
                }               
            //}
            
            //add login log function here
/*          return response()->json([
                'guest_id' => $u->id,
                'uid' => $uid,
                //'token' => $token,
                'code' => 100,
                'message' => 'Token not expired!',
            ]); */                          
        } else {
            //add login log failed attemps function here
            return response()->json([
                'uid' => 'required',
                'code' => -100,
                'message' => 'Guest NOT FOUND!',
            ]);
        }
    }

    public function register(Request $request)
    {
         $validator = Validator::make($request->all(), [
            'txt' => 'required'
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

        $nickname = $credentials->nickname;
        $mnc_id = $credentials->mnc_id;
        $email = $credentials->email;
        $facebook = $credentials->facebook;
        $google = $credentials->google;

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = $nickname.$mnc_id.$email.$google.$facebook;
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        $guest = new Guest;
        
        $u = $guest::where('uid','=',$uid)
            ->where('origin_game_id','=',$game_id)
            ->first();
        if (isset($u->id)) { //if exists

            $u->mnc_id = $mnc_id;
            $u->email = $email;
            $u->facebook = $facebook;
            $u->google = $google;

            if(!$u->save()){
                return response()->json([
                    'code' => -200,
                    'message' => 'Guest UPDATE FAILED!',
                ]);
            }

            $profile = new Profile;
        
            $checkProfile = $profile::where('guest_id','=',$guest_id)
                ->first();

            $checkProfile->nickname = $nickname;

            if(!$checkProfile->save()){
                return response()->json([
                    'code' => -200,
                    'message' => 'Profile UPDATE FAILED!',
                ]);
            }

            $varResponse = $nickname.$mnc_id.$email.$facebook.$google.$facebook;
            $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

            $payload = array(
                'guest_id' => $u->id,
                'game_id' => $game_id,
                'uid' => $uid,
                'mnc_id' => $mnc_id,
                'email' => $email,
                'facebook' => $facebook,
                'google' => $google,
                'uid' => $uid,
                'status' => '1',
                'secret_key' => $u->token,
                'secret_key_expire' => $u->token_expire,
                'signature' => $signatureResponse,
                'x' => $credentials->x
            );

            $message = 'Guest UPDATED!';

            //response
            return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
        } else {
            return response()->json([
                'code' => -100,
                'message' => 'Guest NOT FOUND',
            ]);
        }
    }

    public function setprofile(Request $request)
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

        $nickname = $credentials->nickname;
        $gender = $credentials->gender;
        $country = $credentials->country;

        $birthyear = $credentials->birthyear;
        $birthmonth = $credentials->birthmonth;
        $birthdate = $credentials->birthdate;

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = $nickname.$gender.$country.$birthyear.$birthmonth.$birthdate;
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        //check if already exists
        $profile = new Profile;
        
        $u = $profile::where('guest_id','=',$guest_id)
            ->first();
        if (isset($u->id)) { //if exists

            if (isset($nickname)) $u->nickname = $nickname;
            if (isset($gender)) $u->gender = $gender;
            if (isset($country)) $u->country = $country;
            if (isset($birthyear)) $u->birthyear = $birthyear;
            if (isset($birthmonth)) $u->birthmonth = $birthmonth;
            if (isset($birthdate)) $u->birthdate = $birthdate;

            if ($u->save()) {
                if(!$nickname) $u->nickname = '';
                if(!$gender) $u->gender = '';
                if(!$country) $u->country = '';
                if(!$birthyear) $u->birthyear = 0;
                if(!$birthmonth) $u->birthmonth = 0;
                if(!$birthdate) $u->birthdate = 0;

                $varResponse = $u->nickname.$u->gender.$u->country.$u->birthyear.$u->birthmonth.$u->birthdate;
                $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

                $payload = array(
                    'guest_id' => $guest_id,
                    'nickname' => $u->nickname,
                    'gender' => $u->gender,
                    'country' => $u->country,
                    'birthyear' => $u->birthyear,
                    'birthmonth' => $u->birthmonth,
                    'birthdate' => $u->birthdate,
                    'signature' => $signatureResponse,
                    'x' => $credentials->x
                );

                $message = 'Profile UPDATED successfully!';

                //response
                return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
            } else {
                return response()->json([
                    'guest_id' => $guest_id,
                    'nickname' => $nickname,
                    'gender' => $gender,
                    'country' => $country,
                    'birthyear' => $birthyear,
                    'birthmonth' => $birthmonth,
                    'birthdate' => $birthdate,
                    'signature' => $signatureResponse,
                    'code' => -200,
                    'message' => 'Profile UPDATE FAILED!',
                ]);
            }

        } else {
            //if not
            if(!$nickname) $nickname = '';
            if(!$gender) $gender = '';
            if(!$country) $country = '';
            if(!$birthyear) $birthyear = '';
            if(!$birthmonth) $birthmonth = '';
            if(!$birthdate) $birthdate = '';

            $varResponse = $nickname.$gender.$country.$birthyear.$birthmonth.$birthdate;
            $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

            $dt = Carbon\Carbon::now();
            $now = $dt->toDateTimeString();
            $now = $dt->format('Y-m-d H:i:s');
            $newProfile = Profile::create([
                'guest_id' => $guest_id,
                'nickname' => $nickname,
                'gender' => $gender,
                'country' => $country,
                'birthyear' => $birthyear,
                'birthmonth' => $birthmonth,
                'birthdate' => $birthdate,
                'created_at' => $now
            ]);

            $payload = array(
                'guest_id' => $guest_id,
                'nickname' => $nickname,
                'gender' => $gender,
                'country' => $country,
                'birthyear' => $birthyear,
                'birthmonth' => $birthmonth,
                'birthdate' => $birthdate,
                'signature' => $signatureResponse,
                'x' => $credentials->x
            );

            $message = 'Profile created successfully!';

            //response
            return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
        }
    }

    public function googleplay_overwrite(Request $request){
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

        $email = $credentials->email;
        $google = $credentials->google;

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = $email.$google;
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        $guest = new Guest;

        $user = $guest::select('id', 'email', 'google')
                ->where('google', $google)
                ->where('origin_game_id', $game_id)
                ->first();

        if(isset($user->id)){
            $user->email = NULL;
            $user->google = NULL;

            $user->save();
        }

        $u = $guest::select('id', 'email', 'google')
                ->where('id', $guest_id)
                ->where('origin_game_id', $game_id)
                ->first();

        if (isset($u->id)) { //if exists
            if (isset($email)) $u->email = $email;
            if (isset($google)) $u->google = $google;

            if ($u->save()) {
                $varResponse = $u->email.$u->google;
                $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

                $payload = array(
                    'guest_id' => $guest_id,
                    'uid' => $uid,
                    'email' => $u->email,
                    'google' => $u->google,
                    'signature' => $signatureResponse,
                    'x' => $credentials->x
                );

                $message = 'Google Guest UPDATED successfully!';
                
                return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
            } else {
                return response()->json([
                    'guest_id' => $guest_id,
                    'email' => $email,
                    'google' => $google,
                    'code' => -200,
                    'message' => 'Guest UPDATE FAILED!',
                ]);
            }
        } else { //if not
            return response()->json([
                'code' => -200,
                'message' => 'Guest NOT FOUND!',
            ]);
        }
    }

    public function setData(Request $request)
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
        $data = $credentials->data;

        $ticket = $credentials->ticket;
        $premium = $credentials->premium;

        
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

        $data_dec = json_decode($data);

        $pos = strpos($data, ', "v":1}');

        if($pos == ""){
            return response()->json([
                'code' => -403,
                'message' => 'Invalid Request!',
            ]);
        }

        //save data
        $cek = Data::select('data','id')->where('guest_id',$guest_id)->where('game_id',$game_id)->first();
        if(isset($cek->id)){
            $update = Data::find($guest_id);
            $update->data = $data;
            $save = $update->save();
        }
        else{
            $insert = new Data();
            $insert->game_id = $game_id;
            $insert->guest_id = $guest_id;
            $insert->data = $data;
            $save = $insert->save();
        }

        //save diamond disini
        $saveDiamond = Premium::select('id')->where('status',1)->where('game_id',$game_id)->where('guest_id',$guest_id)->first();
        if(isset($saveDiamond->id)){
            $updateDiamond = Premium::find($guest_id);
            $updateDiamond->premium = $premium;
            $updateDiamond_exe = $updateDiamond->save();
        }
        else{
            $insertDiamond = new Premium();
            $insertDiamond->game_id = $game_id;
            $insertDiamond->guest_id = $guest_id;
            $insertDiamond->premium = $premium;
            $insertDiamond->status = 1;
            $updateDiamond_exe = $insertDiamond->save();
        }

        if($updateDiamond_exe){
            if(isJson($credentials->log_premium)==1){
                $log_premium = json_decode($credentials->log_premium, true);
                if(count($log_premium) > 0){

                    $log_premium_insert = array();
                    foreach($log_premium as $key_log_premium => $value_log_premium){

                        foreach($value_log_premium as $key_value_log_premium => $value_value_log_premium)
                        {
                            if($key_value_log_premium=="created_at")
                            {
                                $created_at = date('Y-m-d H:i:s',$value_value_log_premium);
                                $value_log_premium[$key_value_log_premium] = $created_at;
                                $value_log_premium["updated_at"] = $created_at;
                            }
                        }
                        $log_premium_insert[$key_log_premium] = $value_log_premium;
                    }

                    $savePremiumLog = PremiumLog::insert($log_premium_insert);
                    if($savePremiumLog){
                        
                    }
                }
            }
        }

        //save ticket
        $saveTicket = Currency::select('id')->where('status',1)->where('game_id',$game_id)->where('guest_id',$guest_id)->first();
        if(isset($saveTicket->id)){
            $updateTicket = Currency::find($guest_id);
            $updateTicket->coin = $ticket;
            $saveTicket_exe = $updateTicket->save();
        }
        else{
            $insertTicket = new Currency();
            $insertTicket->game_id = $game_id;
            $insertTicket->guest_id = $guest_id;
            $insertTicket->coin = $ticket;
            $insertTicket->status = 1;
            $saveTicket_exe = $insertTicket->save();
        }

        if($saveTicket_exe){
            //save log disini
            
            if(isJson($credentials->log_currency)==1){
                $log_currency = json_decode($credentials->log_currency, true);
                if(count($log_currency) > 0){
                    //parsing
                    $log_currency_insert = array();
                    foreach($log_currency as $key_log_currency => $value_log_currency){

                        foreach($value_log_currency as $key_value_log_currency => $value_value_log_currency)
                        {
                            if($key_value_log_currency=="created_at")
                            {
                                $created_at = date('Y-m-d H:i:s',$value_value_log_currency);
                                $value_log_currency[$key_value_log_currency] = $created_at;
                                $value_log_currency["updated_at"] = $created_at;
                            }
                        }
                        $log_currency_insert[$key_log_currency] = $value_log_currency;
                    }

                    $saveCurrencyLog = CurrencyLog::insert($log_currency_insert);
                    if($saveCurrencyLog){
                        
                    }
                }
            }
        }

        $varResponse = '';
        $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);


        if($save){
            $payload = array(
                'guest_id' => $guest_id,
                'uid' => $uid,
                'data' => $data,
                'game_id' => $game_id,
                'signature' => $signatureResponse,
                'x' => $credentials->x
            );

            $message = 'Set Data Successfully!';

            return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
        }
        else{
            return response()->json([
                    'code' => -200,
                    'message' => 'Gues Update Data FAILED!',
            ]);
        }
    }

    public function flag(Request $request)
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
        $flag = 2;

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

        $flag_x = Guest::find($guest_id);
        $flag_x->block = $flag;
        $flag_exe = $flag_x->save();

        if($flag_exe){
            return response()->json([
                'code' => 200,
                'message' => 'Success !',
            ]);
        }
        else{
            return response()->json([
                'code' => -200,
                'message' => 'Failed !',
            ]);
        }
    }

    public function getData(Request $request)
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

        $getData = Data::select('id','data')->where('guest_id',$guest_id)->where('game_id',$game_id)->first();

        $data = array();
        if(isset($getData->id)){
            $data['data'] = $getData->data;
        }
        else{
            $newData = Data::create([
                'game_id' => $game_id,
                'guest_id' => $guest_id,
                'data' => ""
            ]);

            $getData = Data::select('data')->where('guest_id',$guest_id)->first();

            $data['data'] = $getData->data;
        }

        $premium = Premium::select('premium')->where('status',1)->where('game_id',$game_id)->where('guest_id',$guest_id)->first();
        if(isset($premium->premium)){
            $data['premium'] = $premium->premium;
        }
        else{
            $data['premium'] = 0;
        }

        //ticket
        $ticket = Currency::select('coin')->where('status',1)->where('game_id',$game_id)->where('guest_id',$guest_id)->first();
        if(isset($ticket->coin)){
            $data['ticket'] = $ticket->ticket;
        }
        else{
            $data['ticket'] = 0;
        }


        $data['google'] = "";
        $googleId = Guest::select('google')->where('origin_game_id',$game_id)->where('id',$guest_id)->first();
        if(isset($googleId->google)){
            $data['google'] = $googleId->google;
        }

        $result = null;
        if(count($data) > 0){
            
            $result = $data;
        }

        $varResponse = '';
        $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

        $payload = array(
            'guest_id' => $guest_id,
            'uid' => $uid,
            'data' => $result,
            'game_id' => $game_id,
            'signature' => $signatureResponse,
            'x' => $credentials->x
        );

        $message = 'Get Data Successfully!';

        return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
    }

    public function load_game(Request $request){
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

        $email = $credentials->email;
        $google = $credentials->google;

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = $email.$google;
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        //yg dikiirm uid hp ke 2
        //ganti uid hp ke 1 jadi uid si hp ke 2
        $guest = new Guest;

        //hp ke 1
        //update uid hp 1 jadi uid hp 2
        $u_old = $guest::select('id', 'uid', 'email', 'google', 'sess_id', 'token')
                ->where('google', $google)
                ->where('origin_game_id', $game_id)
                ->first();

        if($u_old->id != $guest_id){
            //dd($uid);
            $_SESSION['sess_id_old'] = $u_old->sess_id;
            $_SESSION['token_old'] = $u_old->token;
            if(isset($u_old->id)){
                $u_old->uid = $uid;
                $u_old->sess_id = CommonController::generateSessId();
                $u_old->token = '';
                $u_old->save();
            }
        }

        $u = $guest::select('id', 'uid', 'email', 'google', 'sess_id', 'token')
                ->where('id', $guest_id)
                ->where('origin_game_id', $game_id)
                ->first();

        if (isset($u->id)) { //if exists
            if($u_old->id != $guest_id){
                $u->uid = '';
                $u->sess_id = $_SESSION['sess_id_old'];
                $u->token = $_SESSION['token_old'];
            }

            if ($u->save()) {
                $varResponse = $u->email.$u->google;
                $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

                $data = array();
                $dataQue = Data::select('data')->where('game_id',$game_id)->where('guest_id',$guest_id)->first();
                if(isset($dataQue->data)){
                    $data = $dataQue->data;
                }

                $payload = array(
                    'data' => $data,
                    'guest_id' => $guest_id,
                    'guest_id_first' => $u_old->id,
                    'uid' => $uid,
                    'email' => $u->email,
                    'google' => $u->google,
                    'signature' => $signatureResponse,
                    'x' => $credentials->x
                );

                $message = 'Google Guest UPDATED successfully!';

                //response
                if($u_old->id != $guest_id){
                    unset ($_SESSION['sess_id_old']);
                    unset ($_SESSION['token_old']);
                }
                return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
            } else {
                return response()->json([
                    'guest_id' => $guest_id,
                    'email' => $email,
                    'google' => $google,
                    'code' => -200,
                    'message' => 'Guest UPDATE FAILED!',
                ]);
            }
        } else { //if not
            return response()->json([
                'code' => -200,
                'message' => 'Guest NOT FOUND!',
            ]);
        }
    }

    public function googleplay_logout(Request $request){
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

        

        $email = $credentials->email;
        $google = $credentials->google;

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = $email.$google;
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        $guest = new Guest;


        $u = $guest::select('id', 'email', 'google')
                ->where('uid', $uid)
                ->where('origin_game_id', $game_id)
                ->first();

        if (isset($u->id)) { //if exist

            $u->uid = '';

            if ($u->save()) {
                $varResponse = $u->email.$u->google;
                $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

                $payload = array(
                    'guest_id' => $guest_id,
                    'uid' => $uid,
                    'email' => $u->email,
                    'google' => $u->google,
                    'signature' => $signatureResponse,
                    'x' => $credentials->x
                );

                $message = 'Google Guest UPDATED successfully!';
                
                return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
            } else {
                return response()->json([
                    'guest_id' => $guest_id,
                    'email' => $email,
                    'google' => $google,
                    'code' => -200,
                    'message' => 'Guest UPDATE FAILED!',
                ]);
            }
        } else {
            return response()->json([
                'code' => -200,
                'message' => 'Guest NOT FOUND!',
            ]);
        }
    }

    public function googleplay_login(Request $request)
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

        $email = $credentials->email;
        $google = $credentials->google;
        $period = substr($this->now,0,7);

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = $email.$google;
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        $guest = new Guest;

        $u = $guest::select('id', 'email', 'google')
                ->where('google', $google)
                ->where('origin_game_id', $game_id)
                ->first();

        if (isset($u->id)) { //if exist
            //coin


            $cCheck = new Data;
            $c = $cCheck::select('data')->where('game_id','=',$game_id)
                ->where('guest_id','=',$u->id)
                ->first();

            $data = $c->data ?? 0;

            $varResponse = $u->email.$u->google.$data;
            $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

            $payload = array(
                'guest_id' => $u->id,
                'email' => $u->email,
                'google' => $u->google,
                'data' => $data,
                'signature' => $signatureResponse,
                'x' => $credentials->x
            );

            $message = 'Google Guest Found!';

            //response
            return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
        } else { //if not
            $user = $guest::select('id', 'email', 'google')
                ->where('id', $guest_id)
                ->where('uid', $uid)
                ->where('origin_game_id', $game_id)
                ->first();

            if(isset($user->id)){ //if exists
                if (isset($email)) $user->email = $email;
                if (isset($google)) $user->google = $google;

                if ($user->save()) {

                    //coin
                    $cCheck = new Data;
                    $c = $cCheck::select('data')->where('game_id','=',$game_id)
                        ->where('guest_id','=',$user->id)
                        ->first();

                    $data = $c->data ?? 0;

                    $varResponse = $user->email.$user->google.$data;
                    $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

                    $payload = array(
                        'guest_id' => $guest_id,
                        'email' => $user->email,
                        'google' => $user->google,
                        'data' => $data,
                        'signature' => $signatureResponse,
                        'x' => $credentials->x
                    );

                    $message = 'Google Guest UPDATED successfully!';

                    //response
                    return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
                } else {
                    return response()->json([
                        'guest_id' => $guest_id,
                        'email' => $email,
                        'google' => $google,
                        'code' => -200,
                        'message' => 'Guest UPDATE FAILED!',
                    ]);
                }
            } else { //if not
                return response()->json([
                    'code' => -200,
                    'message' => 'Guest NOT FOUND!',
                ]);
            }
        }
    }

    public function getprofile(Request $request)
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

        //check if already exists
        $profile = new Profile;
        
        $u = $profile::where('guest_id','=',$guest_id)
            ->first();
        if (isset($u->id)) { //if exists

            if(!$u->nickname) $u->nickname = '';
            if(!$u->gender) $u->gender = '';
            if(!$u->country) $u->country = '';
            if(!$u->birthyear) $u->birthyear = 0;
            if(!$u->birthmonth) $u->birthmonth = 0;
            if(!$u->birthdate) $u->birthdate = 0;

            $varResponse = $u->nickname.$u->gender.$u->country.$u->birthyear.$u->birthmonth.$u->birthdate;
            $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

            $payload = array(
                'guest_id' => $guest_id,
                'nickname' => $u->nickname,
                'gender' => $u->gender,
                'country' => $u->country,
                'birthyear' => $u->birthyear,
                'birthmonth' => $u->birthmonth,
                'birthdate' => $u->birthdate,
                'created_at' => $u->created_at,
                'signature' => $signatureResponse,
                'x' => $credentials->x
            );

            $message = 'Profile FOUND!';

            //response
            return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
        } else {
            //if not
            return response()->json([
                'code' => -200,
                'message' => 'Profile NOT FOUND!',
            ]); 
        }
    }

    public function info(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'txt' => 'required'
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
        //$token = $request->get('token');
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

        $guest = new Guest;
        
        $r = $guest->select('v_ios', 'v_aos','profiles.guest_id','guests.email','guests.facebook','guests.google','profiles.nickname','profiles.country')
            ->leftJoin('profiles','profiles.guest_id','=','guests.id')
            ->join('games','games.id','=','guests.origin_game_id')
            ->where('guests.uid','=',$uid)
            ->where('guests.id','=',$guest_id)
            ->where('guests.origin_game_id',$game_id)
            //->where('guests.token','=',$token)
            ->take(1)->get();

        
        $email = '';
        $facebook = '';
        $google = '';
        $nickname = '';
        $country = '';
        if(isset($r[0]->email)){$email=$r[0]->email;}
        if(isset($r[0]->facebook)){$facebook=$r[0]->facebook;}
        if(isset($r[0]->google)){$google=$r[0]->google;}
        if(isset($r[0]->nickname)){$nickname=$r[0]->nickname;}
        if(isset($r[0]->country)){$country=$r[0]->country;}

        $varResponse = $email.$facebook.$google.$nickname.$country;
        $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);



        $payload = array(
            'guest_id' => $guest_id,
            'email' => $email,
            'facebook' => $facebook,
            'google' => $google,
            'nickname' => $nickname,
            'country' => $country,
            'signature' => $signatureResponse,
            'v_ios' => $r[0]->v_ios,
            'v_aos' => $r[0]->v_aos,
            'x' => $credentials->x
        );

        $message = 'Guest Found!';


        //response
        return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));          
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'txt' => 'required'
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

        $nickname = $credentials->nickname;

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = $nickname;
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        //check if already exists
        $guestCheck = new Guest;
        
        $u = $guestCheck::where('uid','=',$uid)
            ->where('origin_game_id','=',$game_id)
            ->first();

        if(isset($u->id)){ //if exists
            if($u->save()){

                $dt = Carbon\Carbon::now();
                $now = $dt->toDateTimeString();
                $now = $dt->format('Y-m-d H:i:s');

                $profileCheck = new Profile;

                $p = $profileCheck::where('guest_id', '=', $u->id)
                        ->first();

                if(isset($p->id)){ //if profile exist

                    if (isset($nickname) && ($p->nickname == '' || $p->nickname == NULL)) $p->nickname = $nickname;

                    if($p->save()){

                        if(!$p->nickname) $p->nickname = '';

                        $varResponse = $p->guest_id.$p->nickname;
                        $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

                        $payload = array(
                            'guest_id' => $p->guest_id,
                            'nickname' => $p->nickname,
                            'time' => $now,
                            'signature' => $signatureResponse,
                            'x' => $credentials->x
                        );

                        $message = 'Guest UPDATED successfully!';

                        //response
                        return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
                    } else {
                        return response()->json([
                            'guest_id' => $p->guest_id,
                            'nickname' => $nickname,
                            'code' => -200,
                            'message' => 'Guest UPDATE FAILED!',
                        ]);
                    }
                } else {
                    //if not
                    if(!$nickname) $nickname = '';

                    $varResponse = $u->id.$nickname;
                    $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

                    $dt = Carbon\Carbon::now();
                    $now = $dt->toDateTimeString();
                    $now = $dt->format('Y-m-d H:i:s');
                    $newProfile = Profile::create([
                        'guest_id' => $u->id,
                        'nickname' => $nickname,
                        'created_at' => $now
                    ]);

                    $payload = array(
                        'guest_id' => $u->id,
                        'nickname' => $nickname,
                        'time' => $now,
                        'signature' => $signatureResponse,
                        'x' => $credentials->x
                    );

                    $message = 'Guest created successfully!';

                    //response
                    return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
                }

    //          $varResponse = '';
    //          $signatureResponse = CommonController::generateSignature($varResponse,'guest_log_in',$u->id,$game_id,$uid);

    //          return response()->json([
                //  'guest_id' => $u->id,
                //  'nickname' => $nickname,
                //  'time' => $now,
                //  'code' => 200,
                //  'message' => 'Guest UPDATE FAILED!',
                // ]);

            } else {
                return response()->json([
                    'uid' => $uid,
                    'nickname' => $nickname,
                    'code' => -200,
                    'message' => 'Guest UPDATE FAILED!',
                ]);
            }
        } else {
            //if not
            //dd('masuk');
            $dt = Carbon\Carbon::now();
            $now = $dt->toDateTimeString();
            $now = $dt->format('Y-m-d H:i:s');

            $guest = Guest::create([
                'origin_game_id' => $game_id,
                'uid' => $uid,
                'status' => '1',
                'created_at' => $now
            ]);

            $newProfile = Profile::create([
                'guest_id' => $guest->id,
                'nickname' => $nickname,
                'created_at' => $now
            ]);

            if(!$nickname) $nickname = '';

            $varResponse = $guest->id.$nickname;
            $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

            $payload = array(
                'guest_id' => $guest->id,
                'nickname' => $nickname,
                'time' => $now,
                'signature' => $signatureResponse,
                'x' => $credentials->x
            );

            $message = 'Guest created successfully!';

            //response
            return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
        }
    }
}
