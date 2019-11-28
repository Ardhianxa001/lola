<?php

namespace App\Http\Helpers;

use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Firebase\JWT\ExpiredException;

class Helpers
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    public static function generateToken($user_id) {
        $payload = array(
            'iss' => "lumen-jwt",
            'sub' => $user_id,
            'iat' => time(),
            'exp' => strtotime(date('Y-m-d').' 23:59:59')
            );
        return JWT::encode($payload, env('JWT_SECRET'));
    } 

    public static function generateJwt($payload,$secret_jwt,$algorithm,$token,$sess_id,$chiper_aes)
    {
        $jwt = JWT::encode(json_decode($payload), $secret_jwt, $algorithm);
        $code = ssl_encrypt($jwt,$token,$sess_id,$chiper_aes);

        return $code;
    }

    public static function response($payload, $message, $secret_key, $sess_id)
    {
        $token_jwt_response = JWT::encode($payload, config('key.secret_jwt'), 'HS256');
        $ivlen = openssl_cipher_iv_length(config('key.chiper_aes'));
        $iv = openssl_random_pseudo_bytes($ivlen);
        $iv_base64 = base64_encode($iv);
        $ciphertext = openssl_encrypt($token_jwt_response, config('key.chiper_aes'), base64_decode($secret_key), $options=0, $iv, $tag, $sess_id);


        //return ($r);
        if(isset($payload['code']) && $payload['code'] != 200){
            return response()->json([
                'txt' => $ciphertext.'.'.$iv_base64.'.'.base64_encode($tag),
                'code' => $payload['code'],
                'message' => $message
            ], $payload['code']); 
        } else {
            return response()->json([
                'txt' => $ciphertext.'.'.$iv_base64.'.'.base64_encode($tag),
                'code' => 200,
                'message' => $message
            ], 200);  
        }
    }
}
