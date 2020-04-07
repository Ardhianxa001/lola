<?php

namespace App\Http\Controllers;

use Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\CommonController;
use App\Http\Models\Guest;
use App\Http\Models\Profile;
use App\Http\Models\Data;
use Log;

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

        //txt is required
        $validator = Validator::make($request->all(), [
            'txt' => 'required',
        ]);

        //jika txt tidak ada
        if ($validator->fails()) {

            Log::channel('error')->error(' | auth/login | -401 Invalid Request!');
            return response()->json([
                'code' => -401,
                'message' => 'Invalid Request!',
            ]);
        }

        //txt base 64 deocde
        $txt = base64_decode($request->get('txt'));

        //decrypt txt
        $decryptedData = "";
        openssl_private_decrypt($txt, $decryptedData, config('key.private_key_oaep'), OPENSSL_PKCS1_OAEP_PADDING);
        $data = json_decode($decryptedData);

        //jika decrypt txt gagal
        if($data == NULL){
            Log::channel('error')->error(' | auth/login | -403 Access Denied!');
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

        //jika ada data yang kosong
        if(empty($uid) || empty($game_id) ||empty($time) ||empty($signature)){
                Log::channel('error')->error(' | auth/login | -403 Invalid Request!');
                return response()->json([
                    'code' => -403,
                    'message' => 'Invalid Request!',
                ]);
        }

        //cek signature
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
            Log::channel('error')->error(' | auth/login | -403 Wrong Signature!');
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }
        
        //jika bukan versi 1.0.0
        if($request->v != "1.0.0"){
            //CEK UID FORMAT LAMA
            $uid_original_explode = explode("$",$uid);
            $uid_original = $uid_original_explode[0];
            $cek_uid_old_version = Guest::select('id')->where('uid','=',$uid_original)->where('origin_game_id','=',$game_id)->first();
            if(isset($cek_uid_old_version->id)){
                //UPDATE UID
                $uid_new = $uid.time();
                $update_uid = Guest::find($cek_uid_old_version->id);
                $update_uid->uid = $uid_new;
                $update_uid->save();
                $uid = $uid_new;
            }
        }


        //get data guest
        $guestCheck = new Guest;
        $u = $guestCheck::select('id','sess_id','token','token_expire')->where('uid','=',$uid)->where('origin_game_id','=',$game_id)->first();

        //jika uid exist
        if(isset($u->id))
        {
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

            if($u->save())
            {
                //encrypt AES pake secret_key and key SHA256
                $ivlen = openssl_cipher_iv_length(config('key.chiper_aes'));
                $iv = openssl_random_pseudo_bytes($ivlen);
                $iv_base64 = base64_encode($iv);
                $ciphertext = openssl_encrypt($secret_key, config('key.chiper_aes'), base64_decode($key), $options=0, $iv, $tag, $sess_id);

                //create payload for client
                $payload = array(
                    'txt' => $ciphertext,
                    'iv' => $iv_base64,
                    'tag' => base64_encode($tag),
                    'sess_id' => $sess_id,
                    'guest_id' => $u->id,
                    'uid' => $uid
                );

                //encrypt pake private_key
                $encryptedData = "";
                openssl_private_encrypt(json_encode($payload), $encryptedData, config('key.private_key_pkcs'));
                $encryptedData = base64_encode($encryptedData);

                //untuk ios, cek apakah game center dipakai device lain
                if($request->p=='ios'){
                    if($gameCenterId != '' && $gameCenterId != null){
                        $find_gameCenterId = Guest::select('id')->where('google','G:'.$gameCenterId)->where('origin_game_id','=',$game_id)->where('uid','!=',$uid)->first();
                        if(isset($find_gameCenterId->id)){
                            Log::channel('error')->error(' | auth/login | -302 Used On Another Device!');
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
                    'uid' => $uid,
                    'message' => 'login Successfully!'
                ], 200);

            } else {
                Log::channel('error')->error(' | auth/login | -200 Guest UPDATE FAILED!');
                return response()->json([
                    'uid' => $uid,
                    'nickname' => $nickname,
                    'code' => -200,
                    'message' => 'Guest UPDATE FAILED!',
                ]);
            }
        } else {

            //jika uid tidak exist
            $uid_new = $uid;
            if($request->v!="1.0.0"){
                $uid_new = $uid.time();
            }
            
            $data_secret = array(
                'u' => $uid_new,
                'g' => $game_id,
                't' => $time
            );
            $data_secret_json = json_encode($data_secret);
            $secret_key = base64_encode(substr(hash('sha256', $data_secret_json, TRUE),0,16)); //ambil 16 karakter pertama dari hash sha256

            $data_key = array(
                'g' => $game_id,
                'u' => $uid_new,
                't' => $time
            );
            $data_key_json = json_encode($data_key);
            $key = base64_encode(substr(hash('sha256', $data_key_json, TRUE),0,16));

            //generate sess_id
            $sess_id = CommonController::generateSessId();

            //create guest
            $guest = Guest::create([
                'origin_game_id' => $game_id,
                'uid' => $uid_new,
                'sess_id' => $sess_id,
                'token' => $secret_key,
                'token_expire' => date('Y-m-d H:i:s', strtotime("+10 minutes")), //token expired 10 minutes
                'status' => '1',
                'created_at' => $this->now
            ]);

            //save profile user
            $newProfile = Profile::create([
                'guest_id' => $guest->id,
                'nickname' => '',
                'created_at' => $this->now
            ]);

            //save gamedata
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

            //create payload for client
            $payload = array(
                'txt' => $ciphertext,
                'iv' => $iv_base64,
                'tag' => base64_encode($tag),
                'sess_id' => $sess_id,
                'guest_id' => $guest->id,
                'uid' => $uid_new,
            );

            //encrypt pake private_key
            $encryptedData = "";
            openssl_private_encrypt(json_encode($payload), $encryptedData, config('key.private_key_pkcs'));
            $encryptedData = base64_encode($encryptedData);

            //untuk ios, cek apakah game center dipakai device lain
            if($request->p=='ios'){
                if($gameCenterId != '' && $gameCenterId != null){
                    $find_gameCenterId = Guest::select('id')->where('google','G:'.$gameCenterId)->where('origin_game_id','=',$game_id)->where('uid','!=','')->first();
                    if(isset($find_gameCenterId->id)){
                        Log::channel('error')->error(' | auth/login | -302 Used On Another Device!');
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
                'message' => 'login Successfully!',
                'uid' => $uid_new
            ], 200);
        }
    }
}