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
use App\Http\Models\PremiumLog;
use App\Http\Models\CurrencyLog;
use App\Http\Models\RedeemLog;
use ZipArchive;

class CommonController extends Controller
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

    public function gmt_7($datetime)
    {
        return gmdate('Y-m-d H:i:s',$datetime + (60 * 60 * 7));
    }

    public function get_x(Request $request)
    {
        $data_guest = array();
        if(file_exists('/app/api/gamedata/lola/'.$request->date.'/'.$request->guest_id.'.txt')){
            $text = file_get_contents('/app/api/gamedata/lola/'.$request->date.'/'.$request->guest_id.'.txt');
            $rows = explode("|",$text);
            if(count($rows) > 0){
                for($i=0;$i<count($rows);$i++) //loop rows
                {
                    $column = explode("#",$rows[$i]);
                    if(count($column) == 63)
                    {
                        $data_guest_head['date'] = Self::gmt_7($column[60]);
                        $data_guest_head['data']['coinIncrease'] = $column[0];
                        $data_guest_head['data']['coinDecrease'] = $column[1];
                        $data_guest_head['data']['coinBalance'] = $column[2];
                        $data_guest_head['data']['ticketIncrease'] = $column[3];
                        $data_guest_head['data']['ticketDecrease'] = $column[4];
                        $data_guest_head['data']['ticketBalance'] = $column[5];
                        $data_guest_head['data']['diamondIncrease'] = $column[6];
                        $data_guest_head['data']['diamondDecrease'] = $column[7];
                        $data_guest_head['data']['diamondBalance'] = $column[8];
                        $data_guest_head['data']['highestStage'] = $column[9];
                        $data_guest_head['data']['boosterKikoIncrease'] = $column[10];
                        $data_guest_head['data']['boosterKikoDecrease'] = $column[11];
                        $data_guest_head['data']['boosterKikoBalance'] = $column[12];
                        $data_guest_head['data']['boosterPoliIncrease'] = $column[13];
                        $data_guest_head['data']['boosterPoliDecrease'] = $column[14];
                        $data_guest_head['data']['boosterPoliBalance'] = $column[15];
                        $data_guest_head['data']['boosterPatinoIncrease'] = $column[16];
                        $data_guest_head['data']['boosterPatinoDecrease'] = $column[17];
                        $data_guest_head['data']['boosterPatinoBalance'] = $column[18];
                        $data_guest_head['data']['lifeIncrease'] = $column[19];
                        $data_guest_head['data']['lifeDecrease'] = $column[20];
                        $data_guest_head['data']['lifeBalance'] = $column[21];
                        $data_guest_head['data']['infiniteLivesIncrease'] = $column[22];
                        $data_guest_head['data']['infiniteLivesDecrease'] = $column[23];
                        $data_guest_head['data']['infiniteLivesBalance'] = $column[24];
                        $data_guest_head['data']['successAttempts'] = $column[25];
                        $data_guest_head['data']['failedAttempts'] = $column[26];
                        $data_guest_head['data']['openNormalKitchenSet'] = $column[27];
                        $data_guest_head['data']['openPremiumKitchenSet'] = $column[28];
                        $data_guest_head['data']['dailyLoginStreak'] = $column[29];
                        $data_guest_head['data']['extraMovesWithDiamond'] = $column[30];
                        $data_guest_head['data']['refillLivesWithDiamond'] = $column[31];
                        $data_guest_head['data']['refillLivesWithAds'] = $column[32];
                        $data_guest_head['data']['latestComponentLevel00'] = $column[33];
                        $data_guest_head['data']['latestComponentLevel01'] = $column[34];
                        $data_guest_head['data']['latestComponentLevel02'] = $column[35];
                        $data_guest_head['data']['latestComponentLevel10'] = $column[36];
                        $data_guest_head['data']['latestComponentLevel11'] = $column[37];
                        $data_guest_head['data']['latestComponentLevel12'] = $column[38];
                        $data_guest_head['data']['latestComponentLevel13'] = $column[39];
                        $data_guest_head['data']['latestComponentLevel20'] = $column[40];
                        $data_guest_head['data']['latestComponentLevel21'] = $column[41];
                        $data_guest_head['data']['latestComponentLevel22'] = $column[42];
                        $data_guest_head['data']['latestComponentLevel23'] = $column[43];
                        $data_guest_head['data']['latestComponentLevel24'] = $column[44];
                        $data_guest_head['data']['latestComponentLevel30'] = $column[45];
                        $data_guest_head['data']['latestComponentLevel31'] = $column[46];
                        $data_guest_head['data']['latestComponentLevel32'] = $column[47];
                        $data_guest_head['data']['latestComponentLevel33'] = $column[48];
                        $data_guest_head['data']['latestComponentLevel34'] = $column[49];
                        $data_guest_head['data']['latestComponentLevel40'] = $column[50];
                        $data_guest_head['data']['latestComponentLevel41'] = $column[51];
                        $data_guest_head['data']['latestComponentLevel42'] = $column[52];
                        $data_guest_head['data']['latestComponentLevel43'] = $column[53];
                        $data_guest_head['data']['latestComponentLevel44'] = $column[54];
                        $data_guest_head['data']['latestComponentLevel50'] = $column[55];
                        $data_guest_head['data']['latestComponentLevel51'] = $column[56];

                        $data_guest_head['data']['latestComponentLevel52'] = $column[57];

                        $data_guest_head['data']['latestComponentLevel53'] = $column[58];
                        $data_guest_head['data']['latestComponentLevel54'] = $column[59];
                        $data_guest_head['data']['timestamp'] = $column[60];
                        $data_guest_head['data']['id'] = $column[61];
                        $data_guest_head['data']['apiPath'] = $column[62];
                        $data_guest[] = $data_guest_head;
                    }
                }
            }
        }

        $result = json_encode($data_guest,true);

        return $result;
    }

    public static function generateSessId() {
        $token = base64_encode(openssl_random_pseudo_bytes(20));
        return $token;
    }

    public static function show(Request $request)
    {
        $message = 'Gacha FOUND!';
        $payload = array(
            'game_id' => 1,
            'guest_id' => 1,
            'gacha_type' => 1,
            'gacha_color' => 1,
            'coin' => 1,
            'premium' => 1,
            'premium2' => 1,
            'primary_item' => 1,
            'primary_value' => 1,
            'secondary_item' => 1,
            'secondary_value' => 1,
            'third_item' => 1,
            'third_value' => 1,
            'fourth_item' => 1,
            'fourth_value' => 1,
            'fifth_item' => 1,
            'fifth_value' => 1,
            'sixth_item' => 1,
            'sixth_value' => 1,
            'signature' => 1,
        );

        return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));
    }

    public static function generateToken() {
        $token = bin2hex(random_bytes(64));
        return $token;
    }

    public static function generateJwt(Request $request){ 
        $flag = 2;
        $premium = 50;
        $log_premium = "";
        $ticket = 20;
        $signatureName = "redeem_get";
        $game_id = 2;
        $token = "cGC3PQ8X0UmUhHmTD7TCNw==";
        $sess_id = "vmeHXdI3iHcUy5BLt+nR9g==";
        $uid = "41be6f95e093ee11b2c313da0dcebc083232w12";
        $guest_id = 3639935;
        $nickname = "ardhy";
        $mnc_id = 1;
        $email = 'ardhy.anxa@gmail.com';
        $facebook = 'facebook';
        $google = 'google';
        $gender = '';
        $country = '';
        $birthyear = '';
        $birthmonth = '';
        $birthdate = '';
        $delta = 10;
        $reference = 'ABCDEF';
        $category = "shop";
        $iap_trx_id = 'GPA.3314-1882-8885-19919';
        $sku = 'diamond_tier1';
        $time = 1552636128711;
        $method = 'iap';
        $platform = 'GooglePlay';
        $code = "WADIDAW22";
        $redeem_id = 92;
        $inbox_id = 4898;
        $s = 'G:273288195';

        $iap_trx_ref = 'lijkagegedoihmmjohkcdbph.AO-J1Ow6seLxyy0g4jMlAjtB7Dyuq6lIWUCVH4PKdWp6Kw9KBB6-XEtSNw-XNE2iR9vLe6EL2uoitPyIAwvhl97_or2WRhZ0BY4VKQP0bKiFLP55gWMr20tpUPJHvvbDd5_o85wYrifO';
        $iap_currency = 'IDR';
        $price = 10000;
        $currency = 1;
        $value = 30;
        $camp = "";
        $cid = "";
        $status = 1;
        $ip_client = "";
        $country = "country";
        $p = "aos";
        $v = "v2.1";
        $event_id = 21;
        $date = "2019-08-05";
        $consecutive_day = 5;
        //$flag = "confirm";
        $x = '0#0#352450#0#0#259#0#0#0#300#0#0#7#0#0#5#0#0#4#0#0#5#0#0#0#0#0#0#0#1#0#0#0#4#4#4#0#0#0#0#0#0#0#0#0#0#0#0#0#0#0#0#0#0#0#0#0#0#0#0#1570093468#id:d9764fc9-56c0-455e-ab73-86ac28b35ff4#p:guest/data/get$';

        $data = '{"i":{"coins":0,"tickets":0,"premiums":0,"boosterCounts":{"Cross":0,"Drill":0,"Fireworks":1}}, "p":{"nextLevel":2,"shopProgresses":{}}, "s":{"scores":[1410],"stars":[2]}, "v":1}';

        $payload = array(
            "uid" => $uid,
            "game_id" => $game_id,
            "guest_id" => $guest_id,
            "nickname" => $nickname,
            "mnc_id" => 1,
            "email" => $email,
            "facebook" => $facebook,
            "google" => $google,
            "signature" => md5($code.$signatureName.$guest_id.$game_id.$uid),
            "gender" => $gender,
            "country" => $country,
            "birthyear" => $birthyear,
            "birthmonth" => $birthmonth,
            "birthdate" => $birthdate,
            "data" => $data,
            "reference" => $reference,
            "delta" => $delta,
            "category" => $category,
            "iap_trx_id" => $iap_trx_id,
            "sku" => $sku,
            "time" => $time,
            "method" => $method,
            "platform" => $platform,
            "iap_trx_ref" => $iap_trx_ref,
            "iap_currency" => $iap_currency,
            "price" => $price,
            "currency" => $currency,
            "value" => $value,
            "camp" => $camp,
            "cid" => $cid,
            "status" => $status,
            "ip_client" => $ip_client,
            "country" => $country,
            "code" => $code,
            "event_id" => $event_id,
            "redeem_id" => $redeem_id,
            "inbox_id" => $inbox_id,
            "date" => $date,
            "consecutive_day" => $consecutive_day,
            "flag" => $flag,
            "x" => $x,
            "ticket" => $ticket,
            "premium" => $premium,
            "flag" => $flag,
            "log_premium" => '[{"game_id":2,"guest_id":1012719,"currency":1,"created_at":1569958230,"category":"reward","delta":4,"result":10,"reference":"reference:api-dec: Revive"},{"updated_at":1569958230,"game_id":2,"guest_id":1012719,"currency":1,"created_at":1569958230,"category":"reward","delta":4,"result":10,"reference":"reference:api-dec: Revive"}]',
            "log_currency" => '[{"game_id":2,"guest_id":1012719,"created_at":1569958230,"category":"reward","delta":4,"result":10,"reference":"reference:api-dec: Revive"},{"updated_at":1569958230,"game_id":2,"guest_id":1012719,"created_at":1569958230,"category":"reward","delta":4,"result":10,"reference":"reference:api-dec: Revive"}]',
            "s" => $s
        );

        $code = Helpers::generateJwt(json_encode($payload),config('key.secret_jwt'),'HS256',$token,$sess_id,config('key.chiper_aes'));

        echo $code;
    }

    public static function log_redeem($data){
        $logRedeem = RedeemLog::create($data);
    }

    public static function log_premium($act,$game_id,$guest_id,$category,$currency,$delta,$result,$reference)
    {
        if($act=="dec"){
            if($delta < 0){
                $delta = $delta * -1;
            }
        }
        $log_premium = new PremiumLog();
        $log_premium->game_id = $game_id;
        $log_premium->guest_id = $guest_id;
        $log_premium->category = $category;
        $log_premium->currency = $currency;
        $log_premium->delta = $delta; 
        $log_premium->result = $result;
        $log_premium->reference = 'api-'.$act.': '.$reference;
        $log_premium->save();

        return true;
    }

    public static function log_currency($act,$game_id,$guest_id,$category,$delta,$result,$reference)
    {
        if($act=="dec"){
            if($delta < 0){
                $delta = $delta * -1;
            }
        }
        $log_currency = new CurrencyLog();
        $log_currency->game_id = $game_id;
        $log_currency->guest_id = $guest_id;
        $log_currency->category = $category;
        $log_currency->delta = $delta; 
        $log_currency->result = $result;
        $log_currency->reference = 'api-'.$act.': '.$reference;
        $log_currency->save();

        return true;
    }

    public static function generate_ssl_encrypt(){
        $token = "e2VVnRqMzBbDz0l3ydBy3A==";
        $sess_id = "ECXTUlXNGk4Jpzj759mgDcPAzvQ=";
        $uid = "80035890e1593732c2ca3c1a7d148403";
        $code = Helpers::ssl_encrypt($jwt,$token,$sess_id,config('key.chiper_aes'));
        echo $code;
    }

    public static function generateSignature($var, $name, $guest_id, $game_id, $uid){
        if($var != ''){
            $md5 = md5($var.$name.$guest_id.$game_id.$uid);
        } else {
            $md5 = md5($name.$guest_id.$game_id.$uid);
        }
        return $md5;
    }

    public static function servertime(Request $request)
    {
        $date = date('Y-m-d H:i:s');
        $version = $request->get('v');
        $platform = $request->get('p');
        //$time = $request->get('t');
        $time = strtotime(date('Y-m-d H:i:s'));
        $guests_id = $request->get('g');

        if($version != '' && $platform != '' && $time != '' && $guests_id != '')
        {
            $text = 'v1|'.$guests_id.'|'.$version.'|'.$platform.'|'.$time.'|'.$_SERVER['HTTP_USER_AGENT'].'|'.$date.'|'.$date.'>';

            if(!file_exists('/app/api/gamedata/lola/servertime/lola_'.date('Y').'-'.date('m').'-'.date('d').'.txt'))
            {
                $fp = fopen('/app/api/gamedata/lola/servertime/lola_'.date('Y').'-'.date('m')."-".date('d').".txt","wb");
                fwrite($fp,$text);
                fclose($fp);
                chmod('/app/api/gamedata/lola/servertime/lola_'.date('Y').'-'.date('m').'-'.date('d').'.txt',0777);
            }
            else
            {
                $insert = file_put_contents('/app/api/gamedata/lola/servertime/lola_'.date('Y').'-'.date('m').'-'.date('d').'.txt', $text.PHP_EOL , FILE_APPEND | LOCK_EX);
            }
        }

        return response()->json([
            'code' => 200,
            'message' => 'Success!',
        ]);
    }

    public static function deleteDir($dirPath) {
        if (! is_dir($dirPath)) {
            throw new InvalidArgumentException("$dirPath must be a directory");
        }
        if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') {
            $dirPath .= '/';
        }
        $files = glob($dirPath . '*', GLOB_MARK);
        foreach ($files as $file) {
            if (is_dir($file)) {
                self::deleteDir($file);
            } else {
                unlink($file);
            }
        }
        rmdir($dirPath);
    }

    public function delete_zip_x(Request $request)
    {
        $result = "FAILED";
        if(file_exists('/app/api/gamedata/lola/'.$request->date.'.zip')){
            //unlink('/app/api/gamedata/lola/'.$request->date.'.zip');
            $result = "SUCCESS";
        }

        if(file_exists('/app/api/gamedata/lola/'.$request->date.'/')){
            //Self::deleteDir('/app/api/gamedata/lola/'.$request->date.'/');
            $result = "SUCCESS";
        }

        echo $result;
    }

    public function created_zip_x(Request $request)
    {
        $zip = new \ZipArchive;
        if ($zip->open('/app/api/gamedata/lola/'.$request->date.'.zip', ZipArchive::CREATE) === TRUE)
        {
            if(file_exists('/app/api/gamedata/lola/'.$request->date.'/')){
                if($handle = opendir('/app/api/gamedata/lola/'.$request->date.'/'))
                {
                    while (false !== ($entry = readdir($handle))) 
                    {
                        if ($entry != "." && $entry != "..")
                        {
                            $zip->addFile('/app/api/gamedata/lola/'.$request->date.'/'.$entry);
                        }
                    }
                    closedir($handle);
                }
            }
            $zip->close();
        }

        if(file_exists('/app/api/gamedata/lola/'.$request->date.'.zip')){
            echo "success";
        }
        else{
            echo "gagal";
        }
    }
}
