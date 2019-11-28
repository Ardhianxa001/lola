<?php
namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;
use DesignMyNight\Mongodb\MongodbPassportServiceProvider as Eloquent;

class Mongo extends Eloquent
{
    protected $connection = 'mongodb';
    protected $collection = 'buku';

    protected $fillable = [
        
    ];

    protected $hidden = [
		
    ];
}
