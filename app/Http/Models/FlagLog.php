<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class FlagLog extends Model
{
    protected $connection = 'mysql2';
    protected $table = 'log_flag';
    protected $primaryKey = 'id';

    protected $fillable = [
		'game_id',        
		'guest_id',
        'code',
        'reason',
        'status',
    ];

    protected $hidden = [
        	
    ];

}
