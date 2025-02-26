<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $table = 'currency';
    protected $guarded = ['id'];

    protected $fillable = [
        'game_id',
		'guest_id',
		'coin',
        'status'
    ];

    protected $hidden = [
    ];
}
