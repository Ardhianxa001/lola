<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    protected $connection = 'mysql3';
    protected $table = 'event';
    protected $guarded = ['id'];
    protected $primaryKey = 'id';

    protected $fillable = [
        'redeem_id',
		'event_name',
		'text',
		'sponsor',
        'start_date',
        'end_date',
        'status',
    ];

    protected $hidden = [
    ];
}
