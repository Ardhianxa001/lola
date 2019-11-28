<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Inbox extends Model
{
    protected $connection = 'mysql3';
    protected $table = 'inbox';
    protected $guarded = ['id'];
    protected $primaryKey = 'id';

    protected $fillable = [
        'game_id',
		'redeem_id',
		'text',
		'guest_id',
        'start_date',
        'end_date',
        'platform',
        'country',
        'type',
        'status',
    ];

    protected $hidden = [
    ];
}
