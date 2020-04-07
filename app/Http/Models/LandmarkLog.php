<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class LandmarkLog extends Model
{
    protected $connection = 'mysql2';
    protected $table = 'log_landmark';
    protected $primaryKey = 'id';

    protected $fillable = [
		'game_id',        
		'guest_id',
        'category',
		'landmark_sku',
		'reference',
        'status',
        'created_at',
        'updated_at',
    ];

    protected $hidden = [
        	
    ];

}
