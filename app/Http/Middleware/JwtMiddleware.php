<?php
namespace App\Http\Middleware;

use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use App\Http\Models\Guest;
use Log;

class JwtMiddleware
{
    public $attributes;

    public function __construct()
    {

    }

    public function handle($request, Closure $next, $guard = null)
    {
        $now = date('Y-m-d H:i:s');
        $txt = $request->get('txt');

        if(!$txt) {
            // Unauthorized response if txt not there
            Log::channel('error')->error('JwtMiddleware | -400 Txt not provided');
            return response()->json([
                'error' => 'Txt not provided..'
            ], 401);
        }

        if( strpos($request->get('txt'), '.') == false ) {
            //cek if txt dot not found

            Log::channel('error')->error('JwtMiddleware | -401 Txt not provided');
            return response()->json([
                'error' => 'Txt not provided...'
            ], 401);
        }

        $txt_request = explode('.', $request->get('txt'));

        $a = $txt_request[0] ?? '';
        $b = $txt_request[1] ?? '';
        $c = $txt_request[2] ?? '';
        $d = $txt_request[3] ?? '';

        $rules = array(
            'txt' => 'required',
            'iv' => 'required',
            'tag' => 'required',
            'sess_id' => 'required',
        );

        $array = array(
            'txt' => $a,
            'iv' => $b,
            'tag' => $c,
            'sess_id' => $d,
        );

        $validate = validateArray($rules, $array);

        if ($validate->error) {
            return response()->json([
                'code' => -401,
                'message' => $validate->errors,
            ]);  
        }

        $txt = $txt_request[0];
        $iv = base64_decode($txt_request[1]);
        $tag = base64_decode($txt_request[2]);
        $sess_id = $txt_request[3];

        $guest = new Guest;
        $u = $guest::select('id', 'token', 'token_expire', 'sess_id')->where('sess_id','=',$sess_id)->where('origin_game_id',2)->first();
        
        if (!isset($u->id)) {

            Log::channel('error')->error('JwtMiddleware | -100 Sess ID NOT FOUND');
            return response()->json([
                'code' => -100,
                'message' => 'Sess ID NOT FOUND',
            ]);
        }

        if (strtotime($u->token_expire) <= strtotime($now)) {

            Log::channel('error')->error('JwtMiddleware | '.$u->id.' | -300 Token EXPIRED!');
            return response()->json([
                'code' => -300,
                'message' => 'Token EXPIRED!',
            ]);                 
        }
        

        $secret_key = $u->token;

        $token_jwt = ssl_decrypt($txt, config('key.chiper_aes'), $secret_key, $iv, $tag, $sess_id);

        if($token_jwt == FALSE){

            Log::channel('error')->error('JwtMiddleware | -403 JWT Access Denied!');
            return response()->json([
                'code' => -403,
                'message' => 'JWT Access Denied!',
            ]);
        }

        try {
            $credentials = JWT::decode($token_jwt, config('key.secret_jwt'), ['HS256']);
            $credentials->guest_id = $u->id;

            $credentials->p = '';
            $credentials->v = '';
            $credentials->t = '';
            $credentials->g = '';
            $credentials->now = date('Y-m-d H:i:s');



            if(isset($_GET['p'])){$credentials->p=$_GET['p'];}
            if(isset($_GET['v'])){$credentials->v=$_GET['v'];}
            if(isset($_GET['t'])){$credentials->t=$_GET['t'];}
            

        } catch(ExpiredException $e) {

            Log::channel('error')->error('JwtMiddleware | '.$credentials->guest_id.' | -403 Provided token is expired');
            return response()->json([
                'code' => -400,
                'message' => 'Provided token is expired.'
            ], 404);
        } catch(Exception $e) {

            Log::channel('error')->error('JwtMiddleware | '.$credentials->guest_id.' | -400 An error while decoding token');
            return response()->json([
                'code' => -400,
                'message' => 'An error while decoding token.'
            ], 404);
        }

        //save X DATA
        $x = $credentials->x.'|';
        $pos = strpos($x, '$');

        if($pos == ""){

            Log::channel('error')->error('JwtMiddleware | '.$credentials->guest_id.' | -403 Invalid X Variable');
            return response()->json([
                'code' => -403,
                'message' => 'Invalid X Variable',
            ]);
        }

        try {
            //folder tahun
            if(!file_exists('/gamedata/lolabakery/x/'.date('Y').'/'))
            {
                //folder not exists
                mkdir('/gamedata/lolabakery/x/'.date('Y').'/');
                chmod('/gamedata/lolabakery/x/'.date('Y').'/',0755);
            }

            //folder bulan
            if(!file_exists('/gamedata/lolabakery/x/'.date('Y').'/'.date('m').'/'))
            {
                //folder not exists
                mkdir('/gamedata/lolabakery/x/'.date('Y').'/'.date('m').'/');
                chmod('/gamedata/lolabakery/x/'.date('Y').'/'.date('m').'/',0755);
            }

            //folder tanggal
            if(!file_exists('/gamedata/lolabakery/x/'.date('Y').'/'.date('m').'/'.date('d').'/'))
            {
                //folder not exists
                mkdir('/gamedata/lolabakery/x/'.date('Y').'/'.date('m').'/'.date('d').'/');
                chmod('/gamedata/lolabakery/x/'.date('Y').'/'.date('m').'/'.date('d').'/',0755);
            }

            if(!file_exists('/gamedata/lolabakery/x/'.date('Y').'/'.date('m').'/'.date('d').'/'.$credentials->guest_id.'.txt'))
            {
                $fp = fopen('/gamedata/lolabakery/x/'.date('Y').'/'.date('m').'/'.date('d').'/'.$credentials->guest_id.'.txt',"wb");
                fwrite($fp,$x);
                fclose($fp);
                chmod('/gamedata/lolabakery/x/'.date('Y').'/'.date('m').'/'.date('d').'/'.$credentials->guest_id.'.txt',0755);
            }
            else
            {
                $duplicate = false;
                $request_uri = "";
                $x_explode = explode("#",$credentials->x);
                if(isset($x_explode[24])){
                    $x_timestamp = $x_explode[24];
                    $x_id = $x_explode[25];
                    $cek = file_get_contents('/gamedata/lolabakery/x/'.date('Y').'/'.date('m').'/'.date('d').'/'.$credentials->guest_id.'.txt');
                    $x_pos = strpos($cek, $x_id);

                    if(isset($_SERVER['REQUEST_URI'])){
                        $request_uri = $_SERVER['REQUEST_URI'];
                    }

                    if($x_pos != ""){
                        $duplicate = true;
                    }
                }
                
                if($duplicate==false){
                    $insert = file_put_contents('/gamedata/lolabakery/x/'.date('Y').'/'.date('m').'/'.date('d').'/'.$credentials->guest_id.'.txt', $x.PHP_EOL , FILE_APPEND | LOCK_EX);
                }
                else{
                    Log::channel('error')->error('JwtMiddleware | '.$request_uri.' | '.$x_id.' | '.$credentials->guest_id.' | -403 Duplicate X Variable | '.time().' | '.$x);
                }
            }
        }
        catch(ExpiredException $e) {
            Log::channel('error')->error('JwtMiddleware | '.$credentials->guest_id.' | -200 Failed To Write X Variable');
            return response()->json([
                'code' => -400,
                'message' => 'Provided token is expired.'
            ], 404);
        } catch(Exception $e) {
            Log::channel('error')->error('JwtMiddleware | '.$credentials->guest_id.' | -200 Failed To Write X Variable');
            return response()->json([
                'code' => -400,
                'message' => 'Failed To Write X Variable'
            ], 404);
        }


        
        
        if($credentials->p == "aos"){
            $segment = $request->segments();

            if(count($segment) > 1){
                $signatureName = implode("_",$segment);
            } else {
                $signatureName = $segment[0];
            }
        
            if($signatureName != 'auth_login'){
                $u_login = $guest::select('uid')->where('id','=',$credentials->guest_id)
                ->first();

                if($credentials->uid != $u_login->uid){
                    $login = 0;
                } else {
                    $login = 1;
                }

                if($login==0){

                    Log::channel('error')->error('JwtMiddleware | '.$credentials->guest_id.' | -301 ID Not Match!');
                    return response()->json([
                        'code' => -301,
                        'message' => 'ID Not Match!',
                    ]);
                }
            }
        }

        // add jwe to use in all controllers
        $request->attributes->add(['claim' => $credentials, 'secret_key' => $secret_key, 'sess_id' => $u->sess_id]);
        return $next($request);
    }
}
?>