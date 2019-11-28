<?php

namespace App\Http\Controllers;

use Validator;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Firebase\JWT\ExpiredException;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\CommonController;
use App\Http\Models\Guest;
use App\Http\Models\Profile;
use App\Http\Models\Data;
use Carbon;

class AuthController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->now = date('Y-m-d H:i:s');
    }

    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'txt' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code' => -401,
                'message' => 'Invalid Request!',
            ]);
        }

        $txt = base64_decode($request->get('txt'));

        $decryptedData = "";
        openssl_private_decrypt($txt, $decryptedData, config('key.private_key_oaep'), OPENSSL_PKCS1_OAEP_PADDING);
        $data = json_decode($decryptedData);

        if($data == NULL){
            return response()->json([
                'code' => -403,
                'message' => 'Access Denied!',
            ]);
        }

        $uid = $data->u;
        $game_id = $data->g;
        $time = $data->t;
        $signature = $data->a;
        $gameCenterId = $data->s;

        if(empty($uid) || empty($game_id) ||empty($time) ||empty($signature)){
             return response()->json([
                'code' => -403,
                'message' => 'Invalid Request!',
            ]);
        }

        $segment = $request->segments();

        if(count($segment) > 1){
            $signatureName = implode("_",$segment);
        } else {
            $signatureName = $segment[0];
        }

        $varRequest = $time;

        if($request->p=='aos'){
            $game_id_edit= $game_id;
        }
        elseif($request->p=='ios'){
            $game_id_edit = $game_id.'G:'.$gameCenterId;
        }
        $checkSignature = CommonController::generateSignature($varRequest,$signatureName,'',$game_id_edit,$uid);

        

        if($signature != $checkSignature){
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }


        //check if already exists
        $guestCheck = new Guest;
        
        $u = $guestCheck::select('id','sess_id','token','token_expire')->where('uid','=',$uid)
            ->where('origin_game_id','=',$game_id)
            ->first();

        if(isset($u->id)){
            //generate secret_key
            $data_secret = array(
                'u' => $uid,
                'g' => $game_id,
                't' => $time
            );
            $data_secret_json = json_encode($data_secret);
            $secret_key = base64_encode(openssl_random_pseudo_bytes(16));

            $data_key = array(
                'g' => $game_id,
                'u' => $uid,
                't' => $time
            );

            
            $data_key_json = json_encode($data_key);
            $key = base64_encode(substr(hash('sha256', $data_key_json, TRUE),0,16));

            $sess_id = CommonController::generateSessId();

            //update table
            $u->sess_id = $sess_id;
            $u->token = $secret_key;
            $u->token_expire = date('Y-m-d H:i:s', strtotime("+10 minutes")); //token expired 10 minutes

            if($u->save()){
                //encrypt AES pake secret_key and key SHA256
                $ivlen = openssl_cipher_iv_length(config('key.chiper_aes'));
                $iv = openssl_random_pseudo_bytes($ivlen);
                $iv_base64 = base64_encode($iv);
                $ciphertext = openssl_encrypt($secret_key, config('key.chiper_aes'), base64_decode($key), $options=0, $iv, $tag, $sess_id);

                $payload = array(
                    'txt' => $ciphertext,
                    'iv' => $iv_base64,
                    'tag' => base64_encode($tag),
                    'sess_id' => $sess_id,
                    'guest_id' => $u->id
                );

                //encrypt pake private_key
                $encryptedData = "";
                openssl_private_encrypt(json_encode($payload), $encryptedData, config('key.private_key_pkcs'));
                $encryptedData = base64_encode($encryptedData);

                if($request->p=='ios'){
                    if($gameCenterId != '' && $gameCenterId != null){
                        $find_gameCenterId = Guest::select('id')->where('google','G:'.$gameCenterId)->where('origin_game_id','=',$game_id)->where('uid','!=',$uid)->first();
                        if(isset($find_gameCenterId->id)){
                            return response()->json([
                                'txt' => $encryptedData,
                                'code' => -302,
                                'message' => 'Used On Another Device!',
                            ]);
                        }
                    }
                }
                
                return response()->json([
                    'txt' => $encryptedData,
                    'code' => 200,
                    'message' => 'login Successfully!'
                ], 200);

            } else {
                return response()->json([
                    'uid' => $uid,
                    'nickname' => $nickname,
                    'code' => -200,
                    'message' => 'Guest UPDATE FAILED!',
                ]);
            }
        } else {
            $data_secret = array(
                'u' => $uid,
                'g' => $game_id,
                't' => $time
            );
            $data_secret_json = json_encode($data_secret);
            $secret_key = base64_encode(substr(hash('sha256', $data_secret_json, TRUE),0,16)); //ambil 16 karakter pertama dari hash sha256

            $data_key = array(
                'g' => $game_id,
                'u' => $uid,
                't' => $time
            );
            $data_key_json = json_encode($data_key);
            $key = base64_encode(substr(hash('sha256', $data_key_json, TRUE),0,16));

            $sess_id = CommonController::generateSessId();

            // if($request->p=='ios'){
            //     if($gameCenterId != '' && $gameCenterId != null){
            //         $find_gameCenterId = Guest::select('id')->where('google','G:'.$gameCenterId)->where('origin_game_id','=',$game_id)->where('uid','!=','')->first();
            //         if(isset($find_gameCenterId->id)){

            //             $sess_id = CommonController::generateSessId();
            //             $secret_key = base64_encode(openssl_random_pseudo_bytes(16));
            //             $u_old = Guest::select('id')->where('google','G:'.$gameCenterId)->where('origin_game_id','=',$game_id)->first();
            //             $u_old->sess_id = $sess_id;
            //             $u_old->token = $secret_key;
            //             $u_old->token_expire = date('Y-m-d H:i:s', strtotime("+10 minutes")); 
            //             $u_old->save();

            //             //encrypt AES pake secret_key and key SHA256
            //             $ivlen = openssl_cipher_iv_length(config('key.chiper_aes'));
            //             $iv = openssl_random_pseudo_bytes($ivlen);
            //             $iv_base64 = base64_encode($iv);
            //             $ciphertext = openssl_encrypt($secret_key, config('key.chiper_aes'), base64_decode($key), $options=0, $iv, $tag, $sess_id);

            //             $payload = array(
            //                 'txt' => $ciphertext,
            //                 'iv' => $iv_base64,
            //                 'tag' => base64_encode($tag),
            //                 'sess_id' => $sess_id,
            //                 'guest_id' => $u_old->id
            //             );

            //             //encrypt pake private_key
            //             $encryptedData = "";
            //             openssl_private_encrypt(json_encode($payload), $encryptedData, config('key.private_key_pkcs'));
            //             $encryptedData = base64_encode($encryptedData);

            //             return response()->json([
            //                 'txt' => $encryptedData,
            //                 'code' => -302,
            //                 'message' => 'Used On Another Device!',
            //             ]);
            //         }
            //     }
            // }

            $guest = Guest::create([
                'origin_game_id' => $game_id,
                'uid' => $uid,
                'sess_id' => $sess_id,
                'token' => $secret_key,
                'token_expire' => date('Y-m-d H:i:s', strtotime("+10 minutes")), //token expired 10 minutes
                'status' => '1',
                'created_at' => $this->now
            ]);

            $newProfile = Profile::create([
                'guest_id' => $guest->id,
                'nickname' => '',
                'created_at' => $this->now
            ]);

            $newData = Data::create([
                'game_id' => $game_id,
                'guest_id' => $guest->id,
                'data' => ""
            ]);

            //encrypt AES pake secret_key and key SHA256
            $ivlen = openssl_cipher_iv_length(config('key.chiper_aes'));
            $iv = openssl_random_pseudo_bytes($ivlen);
            $iv_base64 = base64_encode($iv);
            $ciphertext = openssl_encrypt($secret_key, config('key.chiper_aes'), base64_decode($key), $options=0, $iv, $tag, $sess_id);

            $payload = array(
                'txt' => $ciphertext,
                'iv' => $iv_base64,
                'tag' => base64_encode($tag),
                'sess_id' => $sess_id,
                'guest_id' => $guest->id
            );

            //encrypt pake private_key
            $encryptedData = "";
            openssl_private_encrypt(json_encode($payload), $encryptedData, config('key.private_key_pkcs'));
            $encryptedData = base64_encode($encryptedData);

            if($request->p=='ios'){
                if($gameCenterId != '' && $gameCenterId != null){
                    $find_gameCenterId = Guest::select('id')->where('google','G:'.$gameCenterId)->where('origin_game_id','=',$game_id)->where('uid','!=','')->first();
                    if(isset($find_gameCenterId->id)){
                        return response()->json([
                            'txt' => $encryptedData,
                            'code' => -302,
                            'message' => 'Used On Another Device!',
                        ]);
                    }
                }
            }
                
            return response()->json([
                'txt' => $encryptedData,
                'code' => 200,
                'message' => 'login Successfully!'
            ], 200);
        }
    }
}
