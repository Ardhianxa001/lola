<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Redeem extends Model
{
    protected $connection = 'mysql3';
    protected $table = 'redeem';
    protected $guarded = ['id'];

    protected $fillable = [
        'game_id',
        'code',
        'image',
        'text',
        'premium',
        'premium2',
        'coin',
        'reward_type',
        'reward_value',
        'expired',
        'start_date',
        'end_date',
        'sponsor',
        'unlimited',
        'qty',
        'last_position_qty',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
    ];
}
