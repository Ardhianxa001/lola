<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Games extends Model
{
    protected $table = 'games';
    protected $guarded = ['id'];
    protected $primaryKey = 'id';

    protected $fillable = [
        'pub_id',
		'name',
		'status',
		'secret',
        'v_ios',
        'ts_ios',
        'v_aos',
        'ts_aos',
        'bid_ios',
        'bid_aos',
    ];

    protected $hidden = [
    ];
}
