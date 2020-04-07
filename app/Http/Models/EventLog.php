<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    protected $connection = 'mysql2';
    protected $table = 'log_event';
    protected $primaryKey = 'id';

    protected $fillable = [
		'game_id',        
		'guest_id',
        'event_id',
    ];

    protected $hidden = [
        	
    ];

}
