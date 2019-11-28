<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

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
}
