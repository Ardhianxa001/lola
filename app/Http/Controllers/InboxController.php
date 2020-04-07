<?php

namespace App\Http\Controllers;

use DB;
use Helpers;
use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use App\Http\Models\Guest;
use App\Http\Models\Profile;
use App\Http\Models\Inbox;
use App\Http\Models\Premium;
use App\Http\Models\Currency;
use App\Http\Models\Redeem;
use App\Http\Models\Event;
use App\Http\Models\RedeemLog;
use Log;
require_once(config('filesystems.geoip.phar'));
use GeoIp2\Database\Reader;

class InboxController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    public function inbox_claim(Request $request)
    {
        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = $credentials->game_id;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;
        $inbox_id = $credentials->inbox_id;
        $reference = $credentials->reference;

        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),$inbox_id,$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | guest/inbox/claim | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
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

                Log::channel('error')->error($guest_id." | guest/inbox/claim | -200 You've blocked for this game");
                return response()->json([
                    'game_id' => $game_id,
                    'guest_id' => $guest_id,
                    'code' => -200,
                    'message' => "You've blocked for this game",
                ]); 
            }
            
            if($game_id != $redeem->game_id){

                Log::channel('error')->error($guest_id.' | guest/inbox/claim | -200 Claim not for this game!');
                return response()->json([
                    'game_id' => $game_id,
                    'guest_id' => $guest_id,
                    'code' => -200,
                    'message' => 'Claim not for this game!',
                ]); 
            }

            if(strtotime($redeem->end_date) <= strtotime($credentials->now)){

                Log::channel('error')->error($guest_id.' | guest/inbox/claim | -202 Expired!');
                return response()->json([
                    'code' => -202,
                    'message' => 'Expired!',
                ]);
            }

            $rlCheck = new RedeemLog;

            $redeem_log = $rlCheck::where('guest_id', $guest_id)->where('redeem_id', $redeem->id)->where('game_id',$game_id)->first();

            if(isset($redeem_log->id)){

                Log::channel('error')->error($guest_id.' | guest/inbox/claim | -202 Already Redeemed!');
                return response()->json([
                    'code' => -201,
                    'message' => 'Already Redeemed!',
                ]);
            }

            if($redeem->unlimited == 0){
                if($redeem->last_position_qty < 1){

                    Log::channel('error')->error($guest_id.' | guest/inbox/claim | -206 out of stock!');
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
                $cCheck_premium = $pCheck::where('game_id','=',$game_id)->where('guest_id','=',$guest_id)->first();

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

                //CommonController::log_premium('add',$game_id,$guest_id,$category,1,$redeem->premium,$result_premium,$reference); 

                $cCheck_premium->premium = $jumlah_premium;

                if (!$cCheck_premium->save()) {

                    Log::channel('error')->error($guest_id.' | guest/inbox/claim | -200 Premium UPDATE FAILED!');
                    return response()->json([
                        'code' => -200,
                        'message' => 'Premium UPDATE FAILED!',
                    ]);
                }
            }

            if($redeem->coin != 0){
                $cCheck = new Currency;

                $cCheck_currency = $cCheck::where('guest_id', $guest_id)->where('game_id', $game_id)->first();

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

                //CommonController::log_currency('add',$game_id,$guest_id,$category,$redeem->coin,$cCheck_currency->coin,$reference);
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
                'platform' => $credentials->p,
                'hit' => 1,
                'created_at' => $credentials->now
            );

            CommonController::log_redeem($logRedeem);

            // $updateStatusInbox = Inbox::find($inbox_id);
            // $updateStatusInbox->status = 2;
            // $updateStatusInbox->save();

            $varResponse = $redeem->code.$redeem->image.$redeem->text.$redeem->premium;
            $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

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
        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = $credentials->game_id;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;

        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),'',$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | guest/inbox | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
                'message' => 'Wrong Signature!',
            ]);
        }

        $profile = new Profile;
        $checkProfile = $profile::select('country')->where('guest_id','=',$guest_id)->first();
        $country = $checkProfile->country;
        if($country == "" || $country == NULL){
            //geoip
            if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
                $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
            }
            $reader = new Reader(config('filesystems.geoip.database'));
            $record = $reader->city($_SERVER['REMOTE_ADDR']);
            $country = $record->country->isoCode;
        }

        if($country != "ID" && $country != "KR"){
            $country = "US";
        }

        $guest = new Guest;
        
        $u = $guest::where('uid','=',$uid)->where('origin_game_id','=',$game_id)->first();

        if (isset($u->id)) 
        {
            $now = date('Y-m-d H:i:s');

            //coin adalah ticket
            $inbox = DB::connection('mysql3')->select(DB::raw(
            "
                SELECT i.*, r.image, r.text, r.premium, r.coin as ticket, reward_type as item_id, reward_value as item_value FROM inbox i 
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

            $inbox = CommonController::convertJsonToObject($inbox, array('ticket','item_value','item_id','id','type','image','text','premium','end_date','status','guest_id','redeem_id','game_id'));

            $varResponse = '';
            $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

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
            Log::channel('error')->error($guest_id.' | guest/inbox | -100 Guest NOT FOUND');
            return response()->json([
                'code' => -100,
                'message' => 'Guest NOT FOUND',
            ]);
        }
    }

    
}