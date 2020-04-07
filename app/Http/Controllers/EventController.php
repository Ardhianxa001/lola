<?php

namespace App\Http\Controllers;

use DB;
use Helpers;
use Validator;
use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use App\Http\Models\Guest;
use App\Http\Models\Event;
use App\Http\Models\EventLog;
use App\Http\Models\Inbox;
use App\Http\Models\Profile;
use Carbon;
use Log;

class EventController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    public function hit(Request $request){

        //save hit view event
        $hit = Event::find($request->e);
        if(isset($hit->hit_popup)){
            $hit->hit_popup = $hit->hit_popup + 1;
            $hit->save();
        }
        

        //respon
        return response()->json([
            'code' => 200,
            'message' => 'HIT SUCCESS!',
        ]);
    }

    public function hit_web(Request $request){

        //save hit view event
        $hit = Event::find($request->e);
        if(isset($hit->hit_web)){
            $hit->hit_web = $hit->hit_web + 1;
            $hit->save();
        }
        

        //respon
        return response()->json([
            'code' => 200,
            'message' => 'HIT SUCCESS!',
        ]);
    }

    public function hit_web_finish(Request $request){

        //save hit view event finish loading
        $hit = Event::find($request->e);
        if(isset($hit->hit_web_finish)){
            $hit->hit_web_finish = $hit->hit_web_finish + 1;
            $hit->save();
        }
        
        //respon
        return response()->json([
            'code' => 200,
            'message' => 'HIT SUCCESS!',
        ]);
    }

    public function sponsor(Request $request){

        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = 2;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;

        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),'',$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | sponsor | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
                'message' => 'Wrong Signature!',
            ]);
        }

        //get country user
        $profile = new Profile;
        $checkProfile = $profile::select('country')->where('guest_id','=',$guest_id)->first();
        $country = $checkProfile->country;
        if($country == "" || $country == NULL)
        { 
            // if country null detect geoip
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

        //get version user
        $version = $credentials->v;
        $version_explode = explode(".",$version);
        $version_1 = $version_explode[0];
        $version_2 = $version_explode[1];
        $version_3 = $version_explode[2];
        $version = ($version_1*1000) + ($version_2*100) + ($version_3*10);
        $start_date = date("Y-m-d",mktime(0, 0, 0, date("m"), date("d"), date("Y"))).' 00:00:00';

        if($credentials->p=="aos"){
            //jika user aos
            $event = DB::connection('mysql3')->select( DB::raw("
            SELECT url, r.premium, r.coin as ticket, r.reward_type as item_id, r.reward_value as item_value, e.version_aos, event as title, e.id, e.text, e.image, e.sponsor, e.start_date, e.end_date, e.type, e.name, data, e.lokasi_id, e.redirect    
            from event e 
            join list_event le on le.id=e.event_id 
            join redeem r on r.id=e.redeem_id 
            WHERE 
            e.version_aos_int <= '$version' 
            and e.type=1 
            and e.sponsor != '' 
            and e.status=1 
            and e.game_id='$game_id'
            and e.country like '%".$country."%' 
            and (e.platform='$credentials->p' or e.platform='all')

            and e.start_date <= NOW() 
            and e.end_date >= NOW() 

            order by e.start_date asc, e.created_at asc;
            "));
        }
        elseif($credentials->p=="ios"){
            //jika user ios
            $event = DB::connection('mysql3')->select( DB::raw("
            SELECT url, r.premium, r.coin as ticket, r.reward_type as item_id, r.reward_value as item_value, e.version_ios, event as title, e.id, e.text, e.image, e.sponsor, e.start_date, e.end_date, e.type, e.name, data, e.lokasi_id, e.redirect   
            from event e 
            join list_event le on le.id=e.event_id 
            join redeem r on r.id=e.redeem_id 
            WHERE 
            e.version_ios_int <= '$version' 
            and e.type=1 
            and e.sponsor != '' 
            and e.status=1 
            and e.game_id='$game_id'
            and e.country like '%".$country."%' 
            and (e.platform='$credentials->p' or e.platform='all')

            and e.start_date <= NOW() 
            and e.end_date >= NOW() 
            order by e.start_date asc, e.created_at asc;
            "));
        }

        //create signature for client
        $varResponse = "";
        $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

        //create payload for client
        $payload = array(
            'game_id' => $game_id,
            'guest_id' => $guest_id,
            'events' => json_encode($event),
            'signature' => $signatureResponse,
            'code' => 200,
            'x' => $credentials->x
        );

        $message = 'Get Data Sponsor Success!';

        //response
        return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
    }
    
    public function listing(Request $request){

        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = 2;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;

        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),'',$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | event/listing | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
                'message' => 'Wrong Signature!',
            ]);
        }

        //cek apakah user ter block
        $block = Guest::select('block')->where('id', $guest_id)->where('origin_game_id',$game_id)->first();
        if($block->block == 1){
            Log::channel('error')->error($guest_id." | event/listing | -403 You've blocked for this game!");
            return response()->json([
                'game_id' => $game_id,
                'guest_id' => $guest_id,
                'code' => -403,
                'message' => "You've blocked for this game",
            ]); 
        }

        
        $start_date = date("Y-m-d",mktime(0, 0, 0, date("m"), date("d")+14, date("Y"))).' 00:00:00';

        //get data event
        $event = DB::connection('mysql3')->select( DB::raw("
                SELECT r.premium, r.coin as ticket, r.reward_type as item_id, r.reward_value as item_value, e.rule,event as title, e.id, e.text, e.image, e.sponsor, e.start_date, e.end_date, e.type as sponsor_type, e.name  , e.event_id as type, e.redirect  
                from event e join list_event le on le.id=e.event_id 
                join redeem r on r.id=e.redeem_id 
                WHERE 
                e.type != 1 and 
                e.sponsor='' and 
                e.status=1 and 
                e.game_id='2' and 
                (e.start_date <= NOW() or e.start_date >= '$start_date') and 
                (e.platform='$credentials->p' or e.platform='all') 
                order by e.start_date asc, e.created_at asc;
            "));

        //create signature for client
        $varResponse = "";
        $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

        //create payload for client
        $payload = array(
            'game_id' => $game_id,
            'guest_id' => $guest_id,
            'events' => json_encode($event),
            'signature' => $signatureResponse,
            'code' => 200,
            'x' => $credentials->x
        );

        $message = 'Get Data Event Success!';

        //response
        return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
    }

    public function reward(Request $request){

        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = 2;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;
        $event_id = $credentials->event_id;

        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),$event_id,$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | event/reward | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
                'message' => 'Wrong Signature!',
            ]);
        }

        //cek apakah user ter block
        $block = Guest::select('block')->where('id', $guest_id)->first();
        if($block->block == 1){
            Log::channel('error')->error($guest_id." | event/reward | -403 You've blocked for this game!");
            return response()->json([
                'game_id' => $game_id,
                'guest_id' => $guest_id,
                'code' => -200,
                'message' => "You've blocked for this game",
            ]); 
        }

        //get data event dan redeem
        $redeem = Event::
        select('redeem_id','event.game_id','expired','event.start_date','event.end_date','unlimited','last_position_qty','redeem.text')
        ->join('redeem','event.redeem_id','=','redeem.id')
        ->where('redeem.status',1)
        ->where('event.status',1)
        ->where('event.id',$event_id)
        ->first();

        if(!isset($redeem->redeem_id))
        {
            //jika event tidak ditemukan
            Log::channel('error')->error($guest_id.' | event/reward | -200 Event Not Found!');
            return response()->json([
                'game_id' => $game_id,
                'guest_id' => $guest_id,
                'code' => -200,
                'message' => "Event Not Found!",
            ]); 
        }
        else
        {
            //jika game bukan untuk game lola
            if($game_id != $redeem->game_id){
                Log::channel('error')->error($guest_id.' | event/reward | -200 this claim not for this game!');
                return response()->json([
                    'game_id' => $game_id,
                    'guest_id' => $guest_id,
                    'code' => -200,
                    'message' => 'this claim not for this game!',
                ]); 
            }

            if($redeem->expired == 1){
                $max_end_date = date('Y-m-d', strtotime('+30 days', strtotime($redeem->end_date))).' 00:00:00';

                //jika event sudah berakhir
                if(strtotime($max_end_date) <= strtotime($credentials->now)){
                    Log::channel('error')->error($guest_id.' | event/reward | -202 event expired!');
                    return response()->json([
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'code' => -202,
                        'message' => 'event expired!'
                    ]); 
                }

                //jika event belum dimulai
                if(strtotime($credentials->now) <= strtotime($redeem->start_date)){
                    Log::channel('error')->error($guest_id.' | event/reward | -205 Event can only be redeemed after '.date("Y-m-d", strtotime($redeem->start_date)));
                    return response()->json([
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'code' => -205,
                        'message' => 'Event can only be redeemed after '.date("Y-m-d", strtotime($redeem->start_date))
                    ]); 
                }
            }


            //jika stock hadiah sudah habis
            if($redeem->unlimited == 0){
                if($redeem->last_position_qty < 1){
                    Log::channel('error')->error($guest_id.' | event/reward | -206 Event is out of stock!');
                    return response()->json([
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'code' => -206,
                        'message' => 'Event is out of stock!'
                    ]); 
                }
            }


            //jika sudah pernah redeem
            $inbox_log = Inbox::where('game_id',$game_id)->where('redeem_id', $redeem->redeem_id)
            ->where('guest_id',$guest_id)->first();
            if(isset($inbox_log->id)){
                Log::channel('error')->error($guest_id.' | event/reward | -201 Already Redeemed!');
                return response()->json([
                    'game_id' => $game_id,
                    'guest_id' => $guest_id,
                    'code' => -201,
                    'message' => 'Already Redeemed!'
                ]); 
            }

            //get inbox date
            if(strtotime($credentials->now) < strtotime($redeem->end_date)){
                $inbox_end_date = date("Y-m-d",mktime(0, 0, 0, date("m"), date("d")+30, date("Y"))).' 23:59:59';
            }
            elseif(strtotime($credentials->now) > strtotime($redeem->end_date)){
                $inbox_end_date = date('Y-m-d', strtotime('+30 days', strtotime($redeem->end_date))).' 23:59:59';
            }

            //save inbox
            $saveInbox = new Inbox();
            $saveInbox->game_id = $game_id;
            $saveInbox->redeem_id = $redeem->redeem_id;
            $saveInbox->text = $redeem->text;
            $saveInbox->guest_id = $guest_id;
            $saveInbox->start_date = $redeem->start_date;
            $saveInbox->end_date = $inbox_end_date;
            $saveInbox->type = 4;
            $saveInbox->platform = $credentials->p;
            $saveInbox->status = 1;
            $insert = $saveInbox->save();

            if($insert){

                //SAVE LOG
                $saveLog = new EventLog();
                $saveLog->game_id = $game_id;
                $saveLog->guest_id = $guest_id;
                $saveLog->event_id = $event_id;
                $log = $saveLog->save();

                if($log){

                    //create signature for client
                    $varResponse = $event_id;
                    $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

                    //create payload for client
                    $payload = array(
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'event_id' => $event_id,
                        'image' => $redeem->image,
                        'text' => $redeem->text,
                        'signature' => $signatureResponse,
                        'code' => 200,
                        'x' => $credentials->x
                    );

                    $message = 'Event Claim Success!';

                    //response
                    return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
                }
                else{

                    //jika error
                    Log::channel('error')->error($guest_id.' | event/reward | -500 Internal Server Error !');
                    return response()->json([
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'code' => -500,
                        'message' => 'Internal Server Error !'
                    ]); 
                }
            }
        }
    }
}