<?php
namespace App\Http\Models;

use Illuminate\Database\Eloquent\Model;

class Profile extends Model
{
    protected $connection = 'mysql';
    protected $table = 'profiles';
    protected $guarded = ['id'];

    protected $fillable = [
        'guest_id',
		'nickname',
		'gender',
        'country',
		'birthyear',
		'birthmonth',
        'birthdate',
    ];

    protected $hidden = [
    ];
}
