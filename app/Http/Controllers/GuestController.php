<?php

namespace App\Http\Controllers;

use Helpers;
use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use App\Http\Models\Guest;
use App\Http\Models\Data;
use App\Http\Models\Premium;
use App\Http\Models\PremiumLog;
use App\Http\Models\Currency;
use App\Http\Models\CurrencyLog;
use Log;
use DB;

class GuestController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    public function daily_login(Request $request)
    {
        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = $credentials->game_id;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;
        $request_code = $credentials->code;
        $time = $credentials->time;

        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),$request_code.$time,$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | guest/daily_login | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
                'message' => 'Wrong Signature!',
            ]);
        }

        //get data daily_login
        $data = Data::select('request_code','request_time')->where('game_id',$game_id)->where('guest_id',$guest_id)->first();

        $block = Guest::select('block')->where('id', $guest_id)->first();
        if($block->block == 1){
            Log::channel('error')->error($guest_id." | guest/daily_login | -200 You've blocked for this game");
            return response()->json([
                'game_id' => $game_id,
                'guest_id' => $guest_id,
                'code' => -200,
                'message' => "You've blocked for this game",
            ]); 
        }

        //cek valid client
        if($request_code=="" && $data->request_time != 0){
            Log::channel('error')->error($guest_id." | guest/daily_login | -403  Invalid Request. | request code is null but request time is not null");
            return response()->json([
                'code' => -403,
                'message' => 'Invalid Request.',
            ]);
        }

        //new user
        if($request_code=="" && $data->request_time==0){
            $code = 200;
            $message = "Success";
        }
        else{
            if($data->request_code==$request_code && date('Y-m-d',$data->request_time)==date('Y-m-d',$time)){
                Log::channel('error')->error($guest_id." | guest/daily_login | -403  Invalid Request.. | time in database (".date('Y-m-d',$data->request_time).") equal with client time (".date('Y-m-d',$time).")");
                $code = -403;
                $message = "Invalid Request";
            }
            elseif($time <= $data->request_time){

                if(in_array($guest_id,config('whitelist.guest_id'))){
                    $code = 200;
                    $message = "Success";
                }
                else{
                    Log::channel('error')->error($guest_id." | guest/daily_login | -403  Invalid Request...| client time (".$time.") is less than or equal with time in db (".$data->request_time.")");
                    $code = -403;
                    $message = "Invalid Request";
                }
            }
            elseif($data->request_code==$request_code && date('Y-m-d') < date('Y-m-d',$time)){

                if(in_array($guest_id,config('whitelist.guest_id'))){
                    $code = 200;
                    $message = "Success";
                }
                else{
                    Log::channel('error')->error($guest_id." | guest/daily_login | -403  Invalid Request....| client time (".date('Y-m-d',$time).") is greater than now");
                    $code = -403;
                    $message = "Invalid Request";
                }
            }
            else{
                $code = 200;
                $message = "Success";
            }
        }
        

        if($code==200){
            $new_request_code = md5(date('Y-m-d H:i:s'));
            $updateData = Data::where('guest_id',$guest_id)->first();
            $updateData->request_code = $new_request_code;
            $updateData->request_time = $time;
            $save = $updateData->save();


            $varResponse = '';
            $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

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

    public function setData(Request $request)
    {
        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = $credentials->game_id;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;
        $data = $credentials->data;
        $ticket = $credentials->ticket;
        $premium = $credentials->premium;

        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),'',$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | guest/data/set | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
                'message' => 'Wrong Signature!',
            ]);
        }

        $data_dec = json_decode($data);
        $pos = strpos($data, ', "v":1}');

        //jika gamedata tidak lengkap
        if($pos == ""){
            Log::channel('error')->error($guest_id.' | guest/data/set | -403 Invalid X Request!');
            return response()->json([
                'code' => -403,
                'message' => 'Invalid X Request!',
            ]);
        }

        $savedata_status = 0;
        $diamond_status = 0;
        $ticket_status = 0;

        try {
            //save data
            $cek = Data::select('data','id')->where('guest_id',$guest_id)->where('game_id',$game_id)->first();
            if(isset($cek->id)){
                //Log::channel('debug')->debug($guest_id.' | update savedata | '.$data);
                $cek->data = $data;
                $save = $cek->save();
            }
            else{
                $insert = new Data();
                $insert->game_id = $game_id;
                $insert->guest_id = $guest_id;
                $insert->data = $data;
                $save = $insert->save();
                //Log::channel('debug')->debug($guest_id.' | new savedata | '.$data);
            }

            if($save){
                $savedata_status = 1;
            }
        }
        catch (\Exception $e) {
            $getMessage = "";
            if($e->getMessage() != null){
                $getMessage = $e->getMessage();
            }

            Log::channel('error')->error($guest_id.' | guest/data/set | -403 Failed Savedata | '.$getMessage);
            $varResponse = '';
            $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);
            $payload = array(
                'guest_id' => $guest_id,
                'uid' => $uid,
                'data' => $data,
                'game_id' => $game_id,
                'signature' => $signatureResponse,
                'x' => $credentials->x,
                'savedata_status' => 0,
                'diamond_status' => 0,
                'ticket_status' => 0
            );
            $message = 'Set Data Failed!';
            return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
        }

        try{
            //save diamond disini
            $saveDiamond = Premium::select('id')->where('status',1)->where('game_id',$game_id)->where('guest_id',$guest_id)->first();
            if(isset($saveDiamond->id)){
                $updateDiamond = Premium::find($saveDiamond->id);
                $updateDiamond->premium = $premium;
                $updateDiamond_exe = $updateDiamond->save();

                //Log::channel('debug')->debug($guest_id.' | update premium | '.$premium);
            }
            else{
                $insertDiamond = new Premium();
                $insertDiamond->game_id = $game_id;
                $insertDiamond->guest_id = $guest_id;
                $insertDiamond->premium = $premium;
                $insertDiamond->status = 1;
                $updateDiamond_exe = $insertDiamond->save();

                //Log::channel('debug')->debug($guest_id.' | new premium | '.$premium);
            }

            if($updateDiamond_exe){

            }

            //replace guest_id null with guest_id from JWT
            $cek_guest_id_null = strpos($credentials->log_premium, '"guest_id":null,');
            $cek_guest_id_null2 = strpos($credentials->log_premium, '"guest_id":"null",');
            $cek_guest_id_null3 = strpos($credentials->log_premium, '"guest_id":,');

            if($cek_guest_id_null > 0 || $cek_guest_id_null2 > 0 || $cek_guest_id_null3 > 3){
                $text = $credentials->log_premium;
                if(!file_exists('/gamedata/lolabakery/log_premium/'.$guest_id.'.txt'))
                {
                    $fp = fopen('/gamedata/lolabakery/log_premium/'.$guest_id.".txt","wb");
                    fwrite($fp,$text);
                    fclose($fp);
                    chmod('/gamedata/lolabakery/log_premium/'.$guest_id.'.txt',0755);
                }
                else
                {
                    $insert = file_put_contents('/gamedata/lolabakery/log_premium/'.$guest_id.'.txt', $text.PHP_EOL , FILE_APPEND | LOCK_EX);
                }

                $credentials->log_premium = str_replace('"guest_id":null,','"guest_id":'.$guest_id.",",$credentials->log_premium);
                $credentials->log_premium = str_replace('"guest_id":"null",','"guest_id":'.$guest_id.",",$credentials->log_premium);
                $credentials->log_premium = str_replace('"guest_id":,','"guest_id":'.$guest_id.",",$credentials->log_premium);
            }

            $log_premium = json_decode($credentials->log_premium, true);
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

                    if($key_value_log_premium=="iap_trx_id")
                    {
                        unset($value_log_premium[$key_value_log_premium]);
                    }
                }

                $log_premium_insert[$key_log_premium] = $value_log_premium;
            }

            $savePremiumLog = PremiumLog::insert($log_premium_insert);
            if($savePremiumLog){
                $diamond_status = 1;
            }
        }
        catch (\Exception $e) {
            $getMessage_diamond = "";
            if($e->getMessage() != null){
                $getMessage_diamond = $e->getMessage();
            }
            Log::channel('error')->error($guest_id.' | guest/data/set | -403 Failed save diamond | '.$getMessage_diamond);
        }

        try{
            //save ticket
            $saveTicket = Currency::select('id')->where('status',1)->where('game_id',$game_id)->where('guest_id',$guest_id)->first();
            if(isset($saveTicket->id)){
                $updateTicket = Currency::find($saveTicket->id);
                $updateTicket->coin = $ticket;
                $saveTicket_exe = $updateTicket->save();

                //Log::channel('debug')->debug($guest_id.' | update ticket | '.$ticket);
            }
            else{
                $insertTicket = new Currency();
                $insertTicket->game_id = $game_id;
                $insertTicket->guest_id = $guest_id;
                $insertTicket->coin = $ticket;
                $insertTicket->status = 1;
                $saveTicket_exe = $insertTicket->save();

                //Log::channel('debug')->debug($guest_id.' | new ticket | '.$ticket);
            }

            if($saveTicket_exe){

            }

            $cek_guest_id_null_currency = strpos($credentials->log_currency, '"guest_id":null,');
            $cek_guest_id_null_currency2 = strpos($credentials->log_currency, '"guest_id":"null",');
            $cek_guest_id_null_currency3 = strpos($credentials->log_currency, '"guest_id":,');

            if($cek_guest_id_null_currency > 0 || $cek_guest_id_null_currency2 > 0 || $cek_guest_id_null_currency3 > 0){
                $text_currency = $credentials->log_currency;
                if(!file_exists('/gamedata/lolabakery/log_currency/'.$guest_id.'.txt'))
                {
                    $fp = fopen('/gamedata/lolabakery/log_currency/'.$guest_id.".txt","wb");
                    fwrite($fp,$text_currency);
                    fclose($fp);
                    chmod('/gamedata/lolabakery/log_currency/'.$guest_id.'.txt',0755);
                }
                else
                {
                    $insert = file_put_contents('/gamedata/lolabakery/log_currency/'.$guest_id.'.txt', $text_currency.PHP_EOL , FILE_APPEND | LOCK_EX);
                }

                $credentials->log_currency = str_replace('"guest_id":null,','"guest_id":'.$guest_id.",",$credentials->log_currency);
                $credentials->log_currency = str_replace('"guest_id":"null",','"guest_id":'.$guest_id.",",$credentials->log_currency);
                $credentials->log_currency = str_replace('"guest_id":,','"guest_id":'.$guest_id.",",$credentials->log_currency);
            }

            //save log disini
            $log_currency = json_decode($credentials->log_currency, true);
            $log_currency_insert = array();
            foreach($log_currency as $key_log_currency => $value_log_currency)
            {
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
                $ticket_status = 1;
            }
        }
        catch (\Exception $e) {
            $getMessage_ticket = "";
            if($e->getMessage() != null){
                $getMessage_ticket = $e->getMessage();
            }

            Log::channel('error')->error($guest_id.' | guest/data/set | -403 Failed save ticket |'.$getMessage_ticket);
        }

        $varResponse = '';
        $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);
        $payload = array(
            'guest_id' => $guest_id,
            'uid' => $uid,
            'data' => $data,
            'game_id' => $game_id,
            'signature' => $signatureResponse,
            'x' => $credentials->x,
            'savedata_status' => $savedata_status,
            'diamond_status' => $diamond_status,
            'ticket_status' => $ticket_status
        );

        $message = 'Successfully!';
        return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
    }

    // public function setData(Request $request)
    // {
    //     //credential yang sudah di filter oleh middleware
    //     $credentials = $request->get('claim');

    //     $game_id = $credentials->game_id;
    //     $guest_id = $credentials->guest_id;
    //     $uid = $credentials->uid;
    //     $signature = $credentials->signature;
    //     $data = $credentials->data;
    //     $ticket = $credentials->ticket;
    //     $premium = $credentials->premium;

    //     //cek signature
    //     $checkSignature = CommonController::checkSignature($signature,$request->segments(),'',$guest_id,$game_id,$uid);
    //     if($signature != $checkSignature['checkSignature']){
    //         Log::channel('error')->error($guest_id.' | guest/data/set | -403 Wrong Signature!');
    //         return response()->json([
    //             'code' => -401,
    //             'message' => 'Wrong Signature!',
    //         ]);
    //     }

    //     $data_dec = json_decode($data);
    //     $pos = strpos($data, ', "v":1}');

    //     //jika gamedata tidak lengkap
    //     if($pos == ""){
    //         Log::channel('error')->error($guest_id.' | guest/data/set | -403 Invalid X Request!');
    //         return response()->json([
    //             'code' => -403,
    //             'message' => 'Invalid X Request!',
    //         ]);
    //     }

    //     $savedata_status = 0;
    //     $diamond_status = 0;
    //     $ticket_status = 0;

    //     try {
    //         //save data
    //         $cek = Data::select('data','id')->where('guest_id',$guest_id)->where('game_id',$game_id)->first();
    //         if(isset($cek->id)){
    //             //Log::channel('debug')->debug($guest_id.' | update savedata | '.$data);
    //             $cek->data = $data;
    //             $save = $cek->save();
    //         }
    //         else{
    //             $insert = new Data();
    //             $insert->game_id = $game_id;
    //             $insert->guest_id = $guest_id;
    //             $insert->data = $data;
    //             $save = $insert->save();
    //             //Log::channel('debug')->debug($guest_id.' | new savedata | '.$data);
    //         }

    //         if($save){
    //             $savedata_status = 1;
    //         }
    //     }
    //     catch (\Exception $e) {
    //         $getMessage = "";
    //         if($e->getMessage() != null){
    //             $getMessage = $e->getMessage();
    //         }

    //         Log::channel('error')->error($guest_id.' | guest/data/set | -403 Failed Savedata | '.$getMessage);
    //         $varResponse = '';
    //         $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);
    //         $payload = array(
    //             'guest_id' => $guest_id,
    //             'uid' => $uid,
    //             'data' => $data,
    //             'game_id' => $game_id,
    //             'signature' => $signatureResponse,
    //             'x' => $credentials->x,
    //             'savedata_status' => 0,
    //             'diamond_status' => 0,
    //             'ticket_status' => 0
    //         );
    //         $message = 'Set Data Failed!';
    //         return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
    //     }

    //     try{
    //         //save diamond disini
    //         $saveDiamond = Premium::select('id')->where('status',1)->where('game_id',$game_id)->where('guest_id',$guest_id)->first();
    //         if(isset($saveDiamond->id)){
    //             $updateDiamond = Premium::find($saveDiamond->id);
    //             $updateDiamond->premium = $premium;
    //             $updateDiamond_exe = $updateDiamond->save();

    //             //Log::channel('debug')->debug($guest_id.' | update premium | '.$premium);
    //         }
    //         else{
    //             $insertDiamond = new Premium();
    //             $insertDiamond->game_id = $game_id;
    //             $insertDiamond->guest_id = $guest_id;
    //             $insertDiamond->premium = $premium;
    //             $insertDiamond->status = 1;
    //             $updateDiamond_exe = $insertDiamond->save();

    //             //Log::channel('debug')->debug($guest_id.' | new premium | '.$premium);
    //         }

    //         if($updateDiamond_exe){

    //         }

    //         $log_premium = json_decode($credentials->log_premium, true);
    //         $log_premium_insert = array();
    //         foreach($log_premium as $key_log_premium => $value_log_premium){

    //             foreach($value_log_premium as $key_value_log_premium => $value_value_log_premium)
    //             {
    //                 if($key_value_log_premium=="created_at")
    //                 {
    //                     $created_at = date('Y-m-d H:i:s',$value_value_log_premium);
    //                     $value_log_premium[$key_value_log_premium] = $created_at;
    //                     $value_log_premium["updated_at"] = $created_at;
    //                 }

    //                 if($key_value_log_premium=="iap_trx_id")
    //                 {
    //                     unset($value_log_premium[$key_value_log_premium]);
    //                 }
    //             }

    //             $log_premium_insert[$key_log_premium] = $value_log_premium;
    //         }

    //         $savePremiumLog = PremiumLog::insert($log_premium_insert);
    //         if($savePremiumLog){
    //             $diamond_status = 1;
    //         }
    //     }
    //     catch (\Exception $e) {
    //         $getMessage_diamond = "";
    //         if($e->getMessage() != null){
    //             $getMessage_diamond = $e->getMessage();
    //         }
    //         Log::channel('error')->error($guest_id.' | guest/data/set | -403 Failed save diamond | '.$getMessage_diamond);
    //     }

    //     try{
    //         //save ticket
    //         $saveTicket = Currency::select('id')->where('status',1)->where('game_id',$game_id)->where('guest_id',$guest_id)->first();
    //         if(isset($saveTicket->id)){
    //             $updateTicket = Currency::find($saveTicket->id);
    //             $updateTicket->coin = $ticket;
    //             $saveTicket_exe = $updateTicket->save();

    //             //Log::channel('debug')->debug($guest_id.' | update ticket | '.$ticket);
    //         }
    //         else{
    //             $insertTicket = new Currency();
    //             $insertTicket->game_id = $game_id;
    //             $insertTicket->guest_id = $guest_id;
    //             $insertTicket->coin = $ticket;
    //             $insertTicket->status = 1;
    //             $saveTicket_exe = $insertTicket->save();

    //             //Log::channel('debug')->debug($guest_id.' | new ticket | '.$ticket);
    //         }

    //         if($saveTicket_exe){

    //         }

    //         //save log disini
    //         $log_currency = json_decode($credentials->log_currency, true);
    //         $log_currency_insert = array();
    //         foreach($log_currency as $key_log_currency => $value_log_currency)
    //         {
    //             foreach($value_log_currency as $key_value_log_currency => $value_value_log_currency)
    //             {
    //                 if($key_value_log_currency=="created_at")
    //                 {
    //                     $created_at = date('Y-m-d H:i:s',$value_value_log_currency);
    //                     $value_log_currency[$key_value_log_currency] = $created_at;
    //                     $value_log_currency["updated_at"] = $created_at;
    //                 }
    //             }
    //             $log_currency_insert[$key_log_currency] = $value_log_currency;
    //         }

    //         $saveCurrencyLog = CurrencyLog::insert($log_currency_insert);
    //         if($saveCurrencyLog){
    //             $ticket_status = 1;
    //         }
    //     }
    //     catch (\Exception $e) {
    //         $getMessage_ticket = "";
    //         if($e->getMessage() != null){
    //             $getMessage_ticket = $e->getMessage();
    //         }

    //         Log::channel('error')->error($guest_id.' | guest/data/set | -403 Failed save ticket |'.$getMessage_ticket);
    //     }

    //     $varResponse = '';
    //     $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);
    //     $payload = array(
    //         'guest_id' => $guest_id,
    //         'uid' => $uid,
    //         'data' => $data,
    //         'game_id' => $game_id,
    //         'signature' => $signatureResponse,
    //         'x' => $credentials->x,
    //         'savedata_status' => $savedata_status,
    //         'diamond_status' => $diamond_status,
    //         'ticket_status' => $ticket_status
    //     );

    //     $message = 'Successfully!';
    //     return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
    // }

    public function getData(Request $request)
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
            Log::channel('error')->error($guest_id.' | guest/data/get | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
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
        $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

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

    public function info(Request $request)
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
            Log::channel('error')->error($guest_id.' | guest/info | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
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
        $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

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
}