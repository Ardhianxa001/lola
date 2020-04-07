<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Adsense extends Model
{
    protected $table = 'adsense';
    protected $primaryKey = 'id';

    protected $fillable = [
        'game_id',
		'slot',
		'platform',
		'status',
    ];

    protected $hidden = [
    ];
}
