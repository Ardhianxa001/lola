<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class PremiumLog extends Model
{
    protected $connection = 'mysql2';
    protected $table = 'log_premium';
    protected $primaryKey = 'id';

    protected $fillable = [
        'game_id',
		'guest_id',
		'category',
		'currency',
        'delta',
        'result',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
    ];

    public static function insertIgnorePremium($arrayOfArrays) {
        $table = 'log_premium'; //https://github.com/laravel/framework/issues/1436#issuecomment-28985630
        $questionMarks = '';
        $values = [];
        $values = '';
        foreach ($arrayOfArrays as $k => $array) {
            
            $game_id = $array['game_id'];
            $guest_id = $array['guest_id'];
            $currency = $array['currency'];
            $category = $array['category'];
            $delta = $array['delta'];
            $result = $array['result'];
            $reference = $array['reference'];
            $created_at = $array['created_at'];
            $updated_at = $array['updated_at'];

            $values .= "('$game_id','$guest_id','$category','$currency','$delta','$result','$reference','$created_at','$updated_at'),";
            
        }
        $values = substr($values, 0, strlen($values) - 1);
        $query = 'INSERT IGNORE INTO mnc_games_log.' . $table . ' (game_id,guest_id,category,currency,delta,result,reference,created_at,updated_at) VALUES ' . $values;
        return $event = DB::connection('mysql2')->statement($query);
    }
}
