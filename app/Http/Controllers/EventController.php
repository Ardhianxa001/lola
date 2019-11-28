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
use App\Http\Models\Event;
use App\Http\Models\EventLog;
use App\Http\Models\Inbox;
use Carbon;

class EventController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->now = date('Y-m-d H:i:s');

        $this->p = "";
        $this->v = "";
        $this->t = "";
        if(isset($_GET['p'])){$this->p = $_GET['p'];}
        if(isset($_GET['v'])){$this->v = $_GET['v'];}
        if(isset($_GET['t'])){$this->t = $_GET['t'];}
    }

    
    public function listing(Request $request){
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

        $game_id = 2;
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
        //dd($checkSignature);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        
        $start_date = date("Y-m-d",mktime(0, 0, 0, date("m"), date("d")+14, date("Y"))).' 00:00:00';

        //2019-07-19
        //2019-07-20
        //->where('start_date','>=',$start_date)


        // $event = Event::select('rule','event as title','event.id','text','image','sponsor','start_date','end_date','event.type')->
        // join('list_event','list_event.id','=','event.event_id')
        // ->where('event.status',1)->where('event.game_id','>=',$game_id)
        // ->where('start_date','>=',$start_date)->get();

        $event = DB::connection('mysql3')->select( DB::raw("
            SELECT e.rule, event as title, e.id, text, image, sponsor, start_date, end_date, e.type, e.name 
            from event e 
            join list_event le on le.id=e.event_id 
            WHERE e.status=1 and e.game_id='$game_id' and (e.start_date <= NOW() or e.start_date >= '$start_date') 
            and (e.platform='$credentials->p' or e.platform='all')  
            order by e.start_date asc, e.created_at asc;
            "));

        $varResponse = "";
        $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

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

        $game_id = 2;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;
        $event_id = $credentials->event_id;

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = $event_id;
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,$guest_id,$game_id,$uid);
        //dd($checkSignature);

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        $block = Guest::select('block')->where('id', $guest_id)->first();

        if($block->block == 1){
            return response()->json([
                'game_id' => $game_id,
                'guest_id' => $guest_id,
                'code' => -200,
                'message' => "You've blocked for this game",
            ]); 
        }

        $redeem = Event::
        select('redeem_id','event.game_id','expired','event.start_date','event.end_date','unlimited','last_position_qty','redeem.text')
        ->join('redeem','event.redeem_id','=','redeem.id')
        ->where('redeem.status',1)
        ->where('event.status',1)
        ->where('event.id',$event_id)
        ->first();

        if(!isset($redeem->redeem_id))
        {
            return response()->json([
                'game_id' => $game_id,
                'guest_id' => $guest_id,
                'code' => -200,
                'message' => "Event Not Found!",
            ]); 
        }
        else
        {
            if($game_id != $redeem->game_id){
                return response()->json([
                    'game_id' => $game_id,
                    'guest_id' => $guest_id,
                    'code' => -200,
                    'message' => 'this claim not for this game!',
                ]); 
            }

            if($redeem->expired == 1){
                $max_end_date = date('Y-m-d', strtotime('+30 days', strtotime($redeem->end_date))).' 00:00:00';
                if(strtotime($max_end_date) <= strtotime($this->now)){
                    return response()->json([
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'code' => -202,
                        'message' => 'event expired!'
                    ]); 
                }

                if(strtotime($this->now) <= strtotime($redeem->start_date)){
                    return response()->json([
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'code' => -205,
                        'message' => 'Event can only be redeemed after '.date("Y-m-d", strtotime($redeem->start_date))
                    ]); 
                }
            }

            if($redeem->unlimited == 0){
                if($redeem->last_position_qty < 1){
                    return response()->json([
                        'game_id' => $game_id,
                        'guest_id' => $guest_id,
                        'code' => -206,
                        'message' => 'Event is out of stock!'
                    ]); 
                }
            }

            $inbox_log = Inbox::where('game_id',$game_id)->where('redeem_id', $redeem->redeem_id)
            ->where('guest_id',$guest_id)->first();
            if(isset($inbox_log->id)){
                return response()->json([
                    'game_id' => $game_id,
                    'guest_id' => $guest_id,
                    'code' => -201,
                    'message' => 'Already Redeemed!'
                ]); 
            }

            //save inbox
            $saveInbox = new Inbox();

            
            //$end_date = date("Y-m-d",mktime(0, 0, 0, date("m"), date("d")+30, date("Y"))).' 00:00:00';

            if(strtotime($this->now) < strtotime($redeem->end_date)){
                $inbox_end_date = date("Y-m-d",mktime(0, 0, 0, date("m"), date("d")+30, date("Y"))).' 23:59:59';
            }
            elseif(strtotime($this->now) > strtotime($redeem->end_date)){
                $inbox_end_date = date('Y-m-d', strtotime('+30 days', strtotime($redeem->end_date))).' 23:59:59';
            }
            
            $saveInbox->game_id = $game_id;
            $saveInbox->redeem_id = $redeem->redeem_id;
            $saveInbox->text = $redeem->text;
            $saveInbox->guest_id = $guest_id;
            $saveInbox->start_date = $redeem->start_date;
            $saveInbox->end_date = $inbox_end_date;
            $saveInbox->type = 4;
            $saveInbox->platform = $this->p;
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
                    $varResponse = $event_id;
                    $signatureResponse = CommonController::generateSignature($varResponse,$signatureName,$guest_id,$game_id,$uid);

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
