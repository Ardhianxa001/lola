<?php

namespace App\Http\Controllers;

use Helpers;
use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use App\Http\Models\Guest;
use App\Http\Models\Data;
use Log;

class GoogleController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    public function googleplay_overwrite(Request $request){
        
        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = $credentials->game_id;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;
        $email = $credentials->email;
        $google = $credentials->google;

        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),$email.$google,$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | guest/googleplay_overwrite | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
                'message' => 'Wrong Signature!',
            ]);
        }

        $guest = new Guest;

        $user = $guest::select('id', 'email', 'google')->where('google', $google)->where('origin_game_id', $game_id)->first();

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
                $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

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

                Log::channel('error')->error($guest_id.' | guest/googleplay_overwrite | -200 Guest UPDATE FAILED!');
                return response()->json([
                    'guest_id' => $guest_id,
                    'email' => $email,
                    'google' => $google,
                    'code' => -200,
                    'message' => 'Guest UPDATE FAILED!',
                ]);
            }
        } else { //if not
            Log::channel('error')->error($guest_id.' | guest/googleplay_overwrite | -200 Guest NOT FOUND!');
            return response()->json([
                'code' => -200,
                'message' => 'Guest NOT FOUND!',
            ]);
        }
    }

    public function load_game(Request $request){
        
        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = $credentials->game_id;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;
        $email = $credentials->email;
        $google = $credentials->google;

        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),$email.$google,$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | guest/load_game | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
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
                $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

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

                Log::channel('error')->error($guest_id.' | guest/load_game | -200 Guest UPDATE FAILED!');
                return response()->json([
                    'guest_id' => $guest_id,
                    'email' => $email,
                    'google' => $google,
                    'code' => -200,
                    'message' => 'Guest UPDATE FAILED!',
                ]);
            }
        } else {
            Log::channel('error')->error($guest_id.' | guest/load_game | -200 Guest NOT FOUND!');
            return response()->json([
                'code' => -200,
                'message' => 'Guest NOT FOUND!',
            ]);
        }
    }

    public function googleplay_logout(Request $request){
        
        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = $credentials->game_id;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;

        $email = $credentials->email;
        $google = $credentials->google;

        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),$email.$google,$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | guest/googleplay_logout | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
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
                $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

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

                Log::channel('error')->error($guest_id.' | guest/googleplay_logout | -200 Guest UPDATE FAILED!');
                return response()->json([
                    'guest_id' => $guest_id,
                    'email' => $email,
                    'google' => $google,
                    'code' => -200,
                    'message' => 'Guest UPDATE FAILED!',
                ]);
            }
        } else {

            Log::channel('error')->error($guest_id.' | guest/googleplay_logout | -200 Guest NOT FOUND!');
            return response()->json([
                'code' => -200,
                'message' => 'Guest NOT FOUND!',
            ]);
        }
    }

    public function googleplay_login(Request $request)
    {
        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');

        $game_id = $credentials->game_id;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;

        $email = $credentials->email;
        $google = $credentials->google;
        $period = substr($credentials->now,0,7);

        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),$email.$google,$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | guest/googleplay_login | -403 Wrong Signature!');
            return response()->json([
                'code' => -401,
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
            $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

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
                    $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

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

                    Log::channel('error')->error($guest_id.' | guest/googleplay_login | -200 Guest UPDATE FAILED!');
                    return response()->json([
                        'guest_id' => $guest_id,
                        'email' => $email,
                        'google' => $google,
                        'code' => -200,
                        'message' => 'Guest UPDATE FAILED!',
                    ]);
                }
            } else { //if not
                Log::channel('error')->error($guest_id.' | guest/googleplay_login | -200 Guest NOT FOUND!');
                return response()->json([
                    'code' => -200,
                    'message' => 'Guest NOT FOUND!',
                ]);
            }
        }
    }
}