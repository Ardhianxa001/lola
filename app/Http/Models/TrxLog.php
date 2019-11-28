<?php

namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class TrxLog extends Model
{
    protected $connection = 'mysql2';
    protected $table = 'log_trx';
    protected $guarded = ['id'];
    protected $primaryKey = 'id';

    protected $fillable = [
        'game_id',        
        'method',
        'platform',
        'iap_trx_id',
        'iap_trx_ref',
        'iap_currency',
        'price',
        'currency',
        'value',
        'sku',
        'reference',
        'camp',
        'cid',
        'status',
        'ip_client',
        'country',
    ];

    protected $hidden = [
    ];
}
