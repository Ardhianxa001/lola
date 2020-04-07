<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class RedeemLog extends Model
{
    protected $connection = 'mysql2';
    protected $table = 'log_redeem';
    protected $guarded = ['id'];

    protected $fillable = [
		'game_id',        
		'guest_id',
        'redeem_id',
		'platform',
		'hit',
    ];

    protected $hidden = [
        	
    ];

}
