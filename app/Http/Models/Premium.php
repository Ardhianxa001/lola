<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Premium extends Model
{
    protected $connection = 'mysql';
    protected $table = 'premium';
    protected $primaryKey = 'id';

    protected $fillable = [
        'game_id',
		'guest_id',
		'premium',
		'premium2',
    ];

    protected $hidden = [
    ];
}
