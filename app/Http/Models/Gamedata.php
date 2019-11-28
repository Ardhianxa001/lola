<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Gamedata extends Model
{

    protected $table = 'gamedata';
    protected $guarded = ['id'];
    protected $primaryKey = 'id';

    protected $fillable = [
        'game_id',
		'category',
		'version',
        'name',
        'number',
        'data',
        'mandatory'
    ];

    protected $hidden = [
    ];
}
