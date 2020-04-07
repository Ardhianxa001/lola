<?php

namespace App\Http\Controllers;

use Helpers;
use Illuminate\Http\Request;
use App\Http\Models\PremiumLog;
use App\Http\Models\CurrencyLog;
use App\Http\Models\RedeemLog;
use App\Http\Models\Event;

class CommonController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    public function get_servertime(Request $request)
    {
        $path = '/gamedata/lolabakery/servertime/'.substr($request->date,0,4).'/'.substr($request->date,5,2).'/'.substr($request->date,8,2).'.txt';
        if(file_exists($path))
        {
            $result = array();
            $text = file_get_contents($path);
            $rows = explode(">",$text);
            if(count($rows) > 0){
                for($i=0;$i<count($rows)-1;$i++)
                {
                    //rows
                    $rows_data = $rows[$i];
                    $data_explode = explode("|",$rows_data);
                    if(count($data_explode) == 8 || count($data_explode) == 9){
                        $data = array();
                        for($ii=0;$ii < 8;$ii++)
                        {
                            $data['api_version'] = trim($data_explode[0]);
                            $data['guests_id'] = $data_explode[1];
                            $data['version'] = $data_explode[2];
                            $data['platform'] = $data_explode[3];
                            $data['timer'] = $data_explode[4];
                            $data['user_agent'] = $data_explode[5];
                            $data['created_at'] = $data_explode[6];
                            $data['updated_at'] = $data_explode[7];
                        }
                    }
                    $result[] = $data;
                }
            }
        }
        else{
            $result = 'Data Not Found';
        }

        return $result;
    }

    public function get_x_detail(Request $request)
    {
        $path = '/gamedata/lolabakery/x/'.substr($request->date,0,4).'/'.substr($request->date,5,2).'/'.substr($request->date,8,2).'/'.$request->guest_id.'.txt';
        if(file_exists($path))
        {
            $result_guest = array();
            $game_id = 2;
            $text = file_get_contents($path);
            $rows = explode("|",$text);
            if(count($rows) > 0)
            {
                for($i=0;$i<count($rows);$i++) //loop rows
                {
                    $column = explode("#",$rows[$i]);
                    if(count($column) == 28){
                        $data_guest_head = array();
                        $data_guest_head['date'] = gmt_7($column[24]);
                        $data_guest_head['data']['coinIncrease'] = $column[12];
                        $data_guest_head['data']['coinDecrease'] = $column[13];
                        $data_guest_head['data']['coinBalance'] = $column[14];

                        $data_guest_head['data']['ticketIncrease'] = $column[3];
                        $data_guest_head['data']['ticketDecrease'] = $column[4];
                        $data_guest_head['data']['ticketBalance'] = $column[5];

                        $data_guest_head['data']['diamondIncrease'] = $column[0];
                        $data_guest_head['data']['diamondDecrease'] = $column[1];
                        $data_guest_head['data']['diamondBalance'] = $column[2];
                        
                        $data_guest_head['data']['highestStageIncrease'] = $column[6];
                        $data_guest_head['data']['highestStageDecrease'] = $column[7];
                        $data_guest_head['data']['highestStageBalance'] = $column[8];

                        $data_guest_head['data']['totalBoosterIncrease'] = $column[9];
                        $data_guest_head['data']['totalBoosterDecrease'] = $column[10];
                        $data_guest_head['data']['totalBoosterBalance'] = $column[11];

                        $data_guest_head['data']['totalComponentLevelIncrease'] = $column[15];
                        $data_guest_head['data']['totalComponentLevelDecrease'] = $column[16];
                        $data_guest_head['data']['totalComponentLevelBalance'] = $column[17];

                        $data_guest_head['data']['totalBuffIncrease'] = $column[18];
                        $data_guest_head['data']['totalBuffDecrease'] = $column[19];
                        $data_guest_head['data']['totalBuffBalance'] = $column[20];

                        $data_guest_head['data']['lifeIncrease'] = $column[21];
                        $data_guest_head['data']['lifeDecrease'] = $column[22];
                        $data_guest_head['data']['lifeBalance'] = $column[23];

 
                        $data_guest_head['data']['timestamp'] = $column[24];
                        $data_guest_head['data']['id'] = $column[25];
                        $data_guest_head['data']['newData'] = $column[26];
                        $data_guest_head['data']['apiPath'] = $column[27];

                        $result_guest[] = $data_guest_head;
                    }
                }
            }
        }
        else{
            $result_guest[] = 'Data Not Found';
        }

        return json_encode($result_guest,true);
    }

    public function get_x(Request $request)
    {
        $path = '/gamedata/lolabakery/x/'.substr($request->date,0,4).'/'.substr($request->date,5,2).'/'.substr($request->date,8,2).'/';
        if(file_exists($path))
        {
            $result = array();
            if($handle = opendir($path))
            {
                if(!file_exists($path.'done/'))
                {
                    mkdir($path.'done/');
                    chmod($path.'done/',0755);
                }

                $guest_id_execute = array();
                $loop = 1;
                
                while (false !== ($entry = readdir($handle))) 
                {
                    if($entry != 'done' && $entry != '..' && $entry != '.')
                    {
                        $guest_id = str_replace(substr($entry,-4),"",$entry);
                        $game_id = 2;
                        
                        if(!in_array($guest_id, $guest_id_execute) && $loop <= $request->limit)
                        {
                            $filename = $path.$entry;

                            $text = file_get_contents($filename);
                            $rows = explode("|",$text);
                            if(count($rows) > 0){
                                $result_guest = array();
                                $result_guest['guest_id'] = $guest_id;
                                $result_guest['x'] = array();
                                for($i=0;$i<count($rows);$i++) //loop rows
                                {
                                    $column = explode("#",$rows[$i]);
                                    if(count($column) == 28){
                                        $data_guest_head = array();
                                        $data_guest_head['date'] = gmt_7($column[24]);
                                        $data_guest_head['data']['coinIncrease'] = $column[12];
                                        $data_guest_head['data']['coinDecrease'] = $column[13];
                                        $data_guest_head['data']['coinBalance'] = $column[14];

                                        $data_guest_head['data']['ticketIncrease'] = $column[3];
                                        $data_guest_head['data']['ticketDecrease'] = $column[4];
                                        $data_guest_head['data']['ticketBalance'] = $column[5];

                                        $data_guest_head['data']['diamondIncrease'] = $column[0];
                                        $data_guest_head['data']['diamondDecrease'] = $column[1];
                                        $data_guest_head['data']['diamondBalance'] = $column[2];
                                        
                                        $data_guest_head['data']['highestStageIncrease'] = $column[6];
                                        $data_guest_head['data']['highestStageDecrease'] = $column[7];
                                        $data_guest_head['data']['highestStageBalance'] = $column[8];

                                        $data_guest_head['data']['totalBoosterIncrease'] = $column[9];
                                        $data_guest_head['data']['totalBoosterDecrease'] = $column[10];
                                        $data_guest_head['data']['totalBoosterBalance'] = $column[11];

                                        $data_guest_head['data']['totalComponentLevelIncrease'] = $column[15];
                                        $data_guest_head['data']['totalComponentLevelDecrease'] = $column[16];
                                        $data_guest_head['data']['totalComponentLevelBalance'] = $column[17];

                                        $data_guest_head['data']['totalBuffIncrease'] = $column[18];
                                        $data_guest_head['data']['totalBuffDecrease'] = $column[19];
                                        $data_guest_head['data']['totalBuffBalance'] = $column[20];

                                        $data_guest_head['data']['lifeIncrease'] = $column[21];
                                        $data_guest_head['data']['lifeDecrease'] = $column[22];
                                        $data_guest_head['data']['lifeBalance'] = $column[23];

                 
                                        $data_guest_head['data']['timestamp'] = $column[24];
                                        $data_guest_head['data']['id'] = $column[25];
                                        $data_guest_head['data']['newData'] = $column[26];
                                        $data_guest_head['data']['apiPath'] = $column[27];

                                        $result_guest['x'][] = $data_guest_head;
                                    }
                                }
                            }

                            $result[] = $result_guest;
                            $copy = copy($path.$entry, $path.'done/'.$entry);
                            if($copy){
                                unlink($path.$entry);
                            }
                            $loop++;
                            $guest_id_execute[] = $guest_id;
                        }
                        
                    }
                }
            }
        }
        else{
            $result[] = 'Data Not Found';
        }

        return json_encode($result,true);
    }

    public static function generateSessId() {
        $token = base64_encode(openssl_random_pseudo_bytes(20));
        return $token;
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
        $signatureName = $request->segment;
        $game_id = 2;
        $token = "csvX62ILLPCWeMWRAXUArg==";
        $sess_id = "4ItrlfJLzJKYD/FgZsoEdl7V7IE=";
        $uid = "0f1e5de8856e5cb6db51d797ea54fe23$1585530510";
        $guest_id = 1000000;
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
        $iap_trx_id = '3314-1882-8885-19919-test-ardi9';
        $sku = 'diamond_tier1-ardi';
        $time = 1552636128711;
        $method = 'iap';
        $platform = 'GooglePlay';
        $code = "LOLABAKERY";
        $redeem_id = 92;
        $inbox_id = 76;
        $s = 'G:273288195';
        $iap_trx_ref = 'lijkagegedoihmmjohkcdbph.AO-J1Ow6seLxyy0g4jMlAjtB7Dyuq6lIWUCVH4PKdWp6Kw9KBB6-XEtSNw-XNE2iR9vLe6EL2uoitPyIAwvhl97_or2WRhZ0BY4VKQP0bKiFLP55gWMr20tpUPJHvvbDd5_o85wYrifO';
        $iap_currency = 'IDR';
        $price = 10000;
        $currency = 1;
        $value = 2000;
        $camp = "";
        $cid = "";
        $status = 1;
        $ip_client = "";
        $country = "country";
        $p = "aos";
        $v = "v2.1";
        $event_id = 12;
        $date = "2019-08-05";
        $consecutive_day = 5;
        //$flag = "confirm";
        $request_code = "2d22800d9cefaa711bd71b1872a2c239";
        $x = '0#0#0#0#0#3689#0#0#61#0#0#15#0#0#91222638#0#0#5#0#0#0#3#0#3#1574395412#id:2692fdc9#event/listing$';
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
            "signature" => md5($signatureName.$guest_id.$game_id.$uid),
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
            "code" => $request_code,
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
            "log_premium" => '[{"game_id":2,"guest_id":1012719,"currency":1,"created_at":1569958233,"category":"reward","delta":4,"result":10,"reference":"reference:api-dec: Revive"},{"updated_at":1569958230,"game_id":2,"guest_id":1012719,"currency":1,"created_at":1569958231,"category":"reward","delta":4,"result":10,"reference":"reference:api-dec: Revive"}]',
            "log_currency" => '[{"created_at":1585197279,"game_id":2,"guest_id":"1001288","delta":10,"result":10,"category":"gift","reference":"reference:api-add new_account_gift"},{"created_at":1585197333,"game_id":2,"guest_id":"1001288","delta":5,"result":15,"category":"win","reference":"reference:api-add clear_stage_1"},{"created_at":1585197396,"game_id":2,"guest_id":"1001288","delta":15,"result":30,"category":"win","reference":"reference:api-add clear_stage_2"},{"created_at":1585197449,"game_id":2,"guest_id":"1001288","delta":10,"result":40,"category":"win","reference":"reference:api-add clear_stage_3"},{"created_at":1585197500,"game_id":2,"guest_id":"1001288","delta":20,"result":60,"category":"win","reference":"reference:api-add clear_stage_4"},{"created_at":1585197555,"game_id":2,"guest_id":"1001288","delta":5,"result":65,"category":"win","reference":"reference:api-add clear_stage_5"},{"created_at":1585197609,"game_id":2,"guest_id":"1001288","delta":15,"result":80,"category":"win","reference":"reference:api-add clear_stage_6"},{"created_at":1585197691,"game_id":2,"guest_id":"1001288","delta":5,"result":85,"category":"win","reference":"reference:api-add clear_stage_7"},{"created_at":1585197775,"game_id":2,"guest_id":"1001288","delta":5,"result":90,"category":"win","reference":"reference:api-add clear_stage_8"},{"created_at":1585201754,"game_id":2,"guest_id":"1001288","delta":-5,"result":85,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585201759,"game_id":2,"guest_id":"1001288","delta":-6,"result":79,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585201839,"game_id":2,"guest_id":"1001288","delta":10,"result":89,"category":"win","reference":"reference:api-add clear_stage_9"},{"created_at":1585201914,"game_id":2,"guest_id":"1001288","delta":5,"result":94,"category":"win","reference":"reference:api-add clear_stage_10"},{"created_at":1585201975,"game_id":2,"guest_id":"1001288","delta":15,"result":109,"category":"win","reference":"reference:api-add clear_stage_11"},{"created_at":1585202071,"game_id":2,"guest_id":"1001288","delta":20,"result":129,"category":"win","reference":"reference:api-add clear_stage_12"},{"created_at":1585202209,"game_id":2,"guest_id":"1001288","delta":15,"result":144,"category":"win","reference":"reference:api-add clear_stage_13"},{"created_at":1585202307,"game_id":2,"guest_id":"1001288","delta":10,"result":154,"category":"win","reference":"reference:api-add clear_stage_14"},{"created_at":1585205515,"game_id":2,"guest_id":"1001288","delta":15,"result":169,"category":"win","reference":"reference:api-add clear_stage_15"},{"created_at":1585205613,"game_id":2,"guest_id":"1001288","delta":15,"result":184,"category":"win","reference":"reference:api-add clear_stage_16"},{"created_at":1585205687,"game_id":2,"guest_id":"1001288","delta":20,"result":204,"category":"win","reference":"reference:api-add clear_stage_17"},{"created_at":1585208549,"game_id":2,"guest_id":"1001288","delta":15,"result":219,"category":"win","reference":"reference:api-add clear_stage_18"},{"created_at":1585208632,"game_id":2,"guest_id":"1001288","delta":15,"result":234,"category":"win","reference":"reference:api-add clear_stage_19"},{"created_at":1585208726,"game_id":2,"guest_id":"1001288","delta":10,"result":244,"category":"win","reference":"reference:api-add clear_stage_20"},{"created_at":1585208873,"game_id":2,"guest_id":"1001288","delta":15,"result":259,"category":"win","reference":"reference:api-add clear_stage_21"},{"created_at":1585208973,"game_id":2,"guest_id":"1001288","delta":50,"result":309,"category":"win","reference":"reference:api-add clear_stage_22"},{"created_at":1585209084,"game_id":2,"guest_id":"1001288","delta":10,"result":319,"category":"win","reference":"reference:api-add clear_stage_23"},{"created_at":1585209194,"game_id":2,"guest_id":"1001288","delta":25,"result":344,"category":"win","reference":"reference:api-add clear_stage_24"},{"created_at":1585209275,"game_id":2,"guest_id":"1001288","delta":25,"result":369,"category":"win","reference":"reference:api-add clear_stage_25"},{"created_at":1585209420,"game_id":2,"guest_id":"1001288","delta":10,"result":379,"category":"win","reference":"reference:api-add clear_stage_26"},{"created_at":1585209540,"game_id":2,"guest_id":"1001288","delta":25,"result":404,"category":"win","reference":"reference:api-add clear_stage_27"},{"created_at":1585209631,"game_id":2,"guest_id":"1001288","delta":15,"result":419,"category":"win","reference":"reference:api-add clear_stage_28"},{"created_at":1585209788,"game_id":2,"guest_id":"1001288","delta":5,"result":424,"category":"win","reference":"reference:api-add clear_stage_29"},{"created_at":1585209912,"game_id":2,"guest_id":"1001288","delta":50,"result":474,"category":"win","reference":"reference:api-add clear_stage_30"},{"created_at":1585218774,"game_id":2,"guest_id":"1001288","delta":-6,"result":468,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585218774,"game_id":2,"guest_id":"1001288","delta":-6,"result":462,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585218777,"game_id":2,"guest_id":"1001288","delta":-6,"result":456,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585218783,"game_id":2,"guest_id":"1001288","delta":-8,"result":448,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585218784,"game_id":2,"guest_id":"1001288","delta":-8,"result":440,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585218786,"game_id":2,"guest_id":"1001288","delta":-8,"result":432,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585218788,"game_id":2,"guest_id":"1001288","delta":-8,"result":424,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585218789,"game_id":2,"guest_id":"1001288","delta":-9,"result":415,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585218790,"game_id":2,"guest_id":"1001288","delta":-9,"result":406,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585218793,"game_id":2,"guest_id":"1001288","delta":-9,"result":397,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585218793,"game_id":2,"guest_id":"1001288","delta":-9,"result":388,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585218795,"game_id":2,"guest_id":"1001288","delta":-9,"result":379,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585218892,"game_id":2,"guest_id":"1001288","delta":25,"result":404,"category":"win","reference":"reference:api-add clear_stage_31"},{"created_at":1585219010,"game_id":2,"guest_id":"1001288","delta":50,"result":454,"category":"win","reference":"reference:api-add clear_stage_32"},{"created_at":1585219187,"game_id":2,"guest_id":"1001288","delta":10,"result":464,"category":"win","reference":"reference:api-add clear_stage_33"},{"created_at":1585219330,"game_id":2,"guest_id":"1001288","delta":15,"result":479,"category":"win","reference":"reference:api-add clear_stage_34"},{"created_at":1585219436,"game_id":2,"guest_id":"1001288","delta":15,"result":494,"category":"win","reference":"reference:api-add clear_stage_35"},{"created_at":1585219535,"game_id":2,"guest_id":"1001288","delta":125,"result":619,"category":"win","reference":"reference:api-add clear_stage_36"},{"created_at":1585219654,"game_id":2,"guest_id":"1001288","delta":50,"result":669,"category":"win","reference":"reference:api-add clear_stage_37"},{"created_at":1585219800,"game_id":2,"guest_id":"1001288","delta":80,"result":749,"category":"win","reference":"reference:api-add clear_stage_38"},{"created_at":1585219984,"game_id":2,"guest_id":"1001288","delta":40,"result":789,"category":"win","reference":"reference:api-add clear_stage_39"},{"created_at":1585220000,"game_id":2,"guest_id":"1001288","delta":-14,"result":775,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585220001,"game_id":2,"guest_id":"1001288","delta":-14,"result":761,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585220003,"game_id":2,"guest_id":"1001288","delta":-15,"result":746,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585220004,"game_id":2,"guest_id":"1001288","delta":-15,"result":731,"category":"upgrade","reference":"reference:api-dec upgrade_component"},{"created_at":1585220107,"game_id":2,"guest_id":"1001288","delta":80,"result":811,"category":"win","reference":"reference:api-add clear_stage_40"},{"created_at":1585220252,"game_id":2,"guest_id":"1001288","delta":50,"result":861,"category":"win","reference":"reference:api-add clear_stage_41"},{"created_at":1585220438,"game_id":2,"guest_id":"1001288","delta":40,"result":901,"category":"win","reference":"reference:api-add clear_stage_42"},{"created_at":1585265922,"game_id":2,"guest_id":"1001288","delta":80,"result":981,"category":"win","reference":"reference:api-add clear_stage_43"},{"created_at":1585266069,"game_id":2,"guest_id":"1001288","delta":50,"result":1031,"category":"win","reference":"reference:api-add clear_stage_44"},{"created_at":1585266248,"game_id":2,"guest_id":"1001288","delta":75,"result":1106,"category":"win","reference":"reference:api-add clear_stage_45"},{"created_at":1585266439,"game_id":2,"guest_id":"1001288","delta":80,"result":1186,"category":"win","reference":"reference:api-add clear_stage_46"},{"created_at":1585266591,"game_id":2,"guest_id":"1001288","delta":50,"result":1236,"category":"win","reference":"reference:api-add clear_stage_47"},{"created_at":1585266782,"game_id":2,"guest_id":"1001288","delta":100,"result":1336,"category":"win","reference":"reference:api-add clear_stage_48"},{"created_at":1585266921,"game_id":2,"guest_id":"1001288","delta":100,"result":1436,"category":"win","reference":"reference:api-add clear_stage_49"},{"created_at":1585267147,"game_id":2,"guest_id":"1001288","delta":30,"result":1466,"category":"win","reference":"reference:api-add clear_stage_50"}]',
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

    public static function checkSignature($signature,$segments,$signature_format,$guest_id,$game_id,$uid)
    {
        if(count($segments) > 1){
            $signatureName = implode("_",$segments);
        } else {
            $signatureName = $segments[0];
        }

        $result['checkSignature'] = Self::generateSignature($signature_format,$signatureName,$guest_id,$game_id,$uid);
        $result['signatureName'] = $signatureName;

        return $result;
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
        $time = strtotime(date('Y-m-d H:i:s'));
        $guests_id = $request->get('g');

        if($version != '' && $platform != '' && $time != '' && $guests_id != '')
        {
            try {
                $PublicIP = get_client_ip();
                $json     = file_get_contents("http://ipinfo.io/$PublicIP/geo");
                $json     = json_decode($json, true);
                $loc  = $json['loc'];
            }
            catch (\Exception $e) {
                $loc = 0;
            }

            $text = 'v1|'.$guests_id.'|'.$version.'|'.$platform.'|'.$time.'|'.$_SERVER['HTTP_USER_AGENT'].'|'.$date.'|'.$date.'|'.$loc.'>';

            //$text = 'v1|'.$guests_id.'|'.$version.'|'.$platform.'|'.$time.'|'.$_SERVER['HTTP_USER_AGENT'].'|'.$date.'|'.$date.'>';

            //folder tahun
            if(!file_exists('/gamedata/lolabakery/servertime/'.date('Y').'/'))
            {
                //folder not exists
                mkdir('/gamedata/lolabakery/servertime/'.date('Y').'/');
                chmod('/gamedata/lolabakery/servertime/'.date('Y').'/',0755);
            }

            //folder bulan
            if(!file_exists('/gamedata/lolabakery/servertime/'.date('Y').'/'.date('m').'/'))
            {
                //folder not exists
                mkdir('/gamedata/lolabakery/servertime/'.date('Y').'/'.date('m').'/');
                chmod('/gamedata/lolabakery/servertime/'.date('Y').'/'.date('m').'/',0755);
            }

            if(!file_exists('/gamedata/lolabakery/servertime/'.date('Y').'/'.date('m').'/'.date('d').'.txt'))
            {
                $fp = fopen('/gamedata/lolabakery/servertime/'.date('Y').'/'.date('m')."/".date('d').".txt","wb");
                fwrite($fp,$text);
                fclose($fp);
                chmod('/gamedata/lolabakery/servertime/'.date('Y').'/'.date('m').'/'.date('d').'.txt',0755);
            }
            else
            {
                $insert = file_put_contents('/gamedata/lolabakery/servertime/'.date('Y').'/'.date('m').'/'.date('d').'.txt', $text.PHP_EOL , FILE_APPEND | LOCK_EX);
            }
        }

        return response()->json([
            'code' => 200,
            'message' => 'Success!',
        ]);
    }

    public static function convertJsonToObject($object, $field) 
    {
        $result = array();
        foreach ($object as $key => $val) {
            $data   = new \stdClass();

            //Field
            foreach ($field as $val_field) {
                $data->$val_field = $val->$val_field;
                if($val->type==4){
                    $event = Event::select('text','image','sponsor')->where('redeem_id',$val->redeem_id)->where('status',1)->first();
                    if(isset($event->id)){
                        $data->event_text = $event->text;
                        $data->event_image = $event->image;
                        $data->event_sponsor = $event->sponsor;
                    }
                }

                //cek log redeem
                $log_redeem = RedeemLog::select('id')->where('guest_id', $val->guest_id)->where('redeem_id', $val->redeem_id)->where('game_id',$val->game_id)->first();
                if(isset($log_redeem->id)){
                    $data->status = 2;
                }
                else{
                    $data->status = 1;
                }
            }
            
            $result[] = $data;
        }
        return $result;
    }
}