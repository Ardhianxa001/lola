<?php
namespace App\Http\Middleware;

use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use App\Http\Models\Guest;

class JwtMiddleware
{
    public $attributes;

    public function __construct()
    {
        // if (empty($_SERVER['PHP_AUTH_USER'])) {
        //     Self::gagal();
        // } else {
        //     if (!Self::validate_user()){
        //         Self::gagal();
        //     }
        // }
    }

    private function validate_user() {
        if ($_SERVER['PHP_AUTH_USER']) {
            $username = $_SERVER['PHP_AUTH_USER'];
            $password = $_SERVER['PHP_AUTH_PW'];

            if ($username == env('AUTH_USERNAME') && $password == env('AUTH_PASSWORD')) {
                return true;
            }

            return false;
        }

        return false;
    }
    

    private function gagal() {
        header('WWW-Authenticate: Basic realm="Authentication user"');
        header('HTTP/1.0 401 Unauthorized');

        echo json_encode(array('error' => 'Sorry you don\'t have authorize to see this content'),JSON_PRETTY_PRINT);
        exit;
    }

    public function handle($request, Closure $next, $guard = null)
    {
        $now = date('Y-m-d H:i:s');

        $txt = $request->get('txt');

        if(!$txt) {
            // Unauthorized response if txt not there
            return response()->json([
                'error' => 'Txt not provided..'
            ], 401);
        }

        if( strpos($request->get('txt'), '.') == false ) {
            //cek if txt dot not found
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

        //query with JWT



        if($_SERVER['HTTP_USER_AGENT']=="PostmanRuntime/7.18.0"){
            $sess_id = "3WRt+uUIpjhadO7ElPMlMpnpF+A=";
        }

        $u = $guest::select('id', 'token', 'token_expire', 'sess_id')->where('sess_id','=',$sess_id)->where('origin_game_id',2)->first();
        
        if (!isset($u->id)) {
            return response()->json([
                'code' => -100,
                'message' => 'Sess ID NOT FOUND',
            ]);
        }

        if($_SERVER['HTTP_USER_AGENT'] != "PostmanRuntime/7.18.0"){
            if (strtotime($u->token_expire) <= strtotime($now)) {
                return response()->json([
                    'code' => -300,
                    'message' => 'Token EXPIRED!',
                ]);                 
            }
        }

        $secret_key = $u->token;
        if($_SERVER['HTTP_USER_AGENT']=="PostmanRuntime/7.17.1"){
            $secret_key = "iODGJQG1BkgdRxDg02TRYw==";
        }

        $token_jwt = ssl_decrypt($txt, config('key.chiper_aes'), $secret_key, $iv, $tag, $sess_id);

        if($token_jwt == FALSE){
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

            if(isset($_GET['p'])){$credentials->p=$_GET['p'];}
            if(isset($_GET['v'])){$credentials->v=$_GET['v'];}
            if(isset($_GET['t'])){$credentials->t=$_GET['t'];}
            

        } catch(ExpiredException $e) {
            return response()->json([
                'code' => -400,
                'message' => 'Provided token is expired.'
            ], 404);
        } catch(Exception $e) {
            return response()->json([
                'code' => -400,
                'message' => 'An error while decoding token.'
            ], 404);
        }

        //save X DATA
        $x = $credentials->x.'|';
        $pos = strpos($x, '$');

        if($pos == ""){
            return response()->json([
                'code' => -403,
                'message' => 'Invalid X Variable',
            ]);
        }

        if(!file_exists('/app/api/gamedata/lola/'.date('Y').'-'.date('m').'-'.date('d').'/'))
        {
        	//folder not exists
        	mkdir('/app/api/gamedata/lola/'.date('Y').'-'.date('m').'-'.date('d').'/');
        	chmod('/app/api/gamedata/lola/'.date('Y').'-'.date('m').'-'.date('d').'/',0777);
        }

    	//folder exists
    	if(!file_exists('/app/api/gamedata/lola/'.date('Y').'-'.date('m').'-'.date('d').'/'.$credentials->guest_id.'.txt'))
        {
            $fp = fopen('/app/api/gamedata/lola/'.date('Y').'-'.date('m').'-'.date('d').'/'.$credentials->guest_id.'.txt',"wb");
            fwrite($fp,$x);
            fclose($fp);
            chmod('/app/api/gamedata/lola/'.date('Y').'-'.date('m').'-'.date('d').'/'.$credentials->guest_id.'.txt',0777);
        }
        else
        {
            $insert = file_put_contents('/app/api/gamedata/lola/'.date('Y').'-'.date('m').'-'.date('d').'/'.$credentials->guest_id.'.txt', $x.PHP_EOL , FILE_APPEND | LOCK_EX);
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