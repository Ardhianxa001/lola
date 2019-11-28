<?php
namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    protected $connection = 'mysql';
    protected $table = 'guests';
    protected $guarded = ['id'];

    protected $fillable = [
        'mnc_id',
        'origin_game_id',
        'email',
        'facebook',
        'google',
        'uid',
        'group_no',
        'block',
        'status',
        'sess_id',
        'last_ip',
        'ip_country',
        'token',
        'token_expire',
    ];

    protected $hidden = [
		'token',
		'token_expire',
    ];
}
