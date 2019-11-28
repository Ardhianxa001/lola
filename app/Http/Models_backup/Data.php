<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Data extends Model
{

    protected $table = 'savedata';
    protected $guarded = ['id'];
    protected $primaryKey = 'guest_id';

    protected $fillable = [
        'game_id',
		'guest_id',
		'data',
    ];

    protected $hidden = [
    ];
}
