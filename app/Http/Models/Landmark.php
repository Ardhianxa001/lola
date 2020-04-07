<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Landmark extends Model
{
    protected $connection = 'mysql';
    protected $table = 'landmark';
    protected $primaryKey = 'id';

    protected $fillable = [
        'game_id',
		'guest_id',
		'landmark_sku',
		'status',
    ];

    protected $hidden = [
    ];
}
