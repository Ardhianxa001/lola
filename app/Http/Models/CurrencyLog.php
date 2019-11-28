<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyLog extends Model
{
    protected $connection = 'mysql2';
    protected $table = 'log_currency';
    protected $primaryKey = 'id';

    protected $fillable = [
        'game_id',
		'guest_id',
		'category',
        'delta',
        'result',
        'created_at',
        'updated_at'
    ];

    protected $hidden = [
    ];
}
