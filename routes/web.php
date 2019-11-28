<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
	    return "okk";
});

//route without guest_id
$router->get('mongodb', ['as' => 'profile', 'uses' => 'GuestController@mongodb']);
$router->post('get_x', ['as' => 'profile', 'uses' => 'CommonController@get_x']);
$router->post('created_zip_x', ['as' => 'created-zip-x', 'uses' => 'CommonController@created_zip_x']);
$router->post('delete_zip_x', ['as' => 'delete-zip-x', 'uses' => 'CommonController@delete_zip_x']);
$router->get('generatejwt', ['as' => 'profile', 'uses' => 'CommonController@generateJwt']);

$router->post('auth/login', ['middleware' => ['global'], 'as' => 'profile', 'uses' => 'AuthController@login']);
$router->post('servertime', ['middleware' => ['global'], 'as' => 'servertime', 'uses' => 'CommonController@servertime']);
$router->get('gamedata', ['middleware' => ['global'], 'as' => 'profile', 'uses' => 'GamedataController@index']);
// $router->get('tokenrefresh', ['middleware' => 'global', 'as' => 'profile', 'uses' => 'CommonController@generateToken']);


$router->group(['prefix' => '', 'middleware' => ['jwt','pvt','global']], function () use ($router) {
	// $router->post('guest/log_in', [
	// 	    'middleware' => 'global', 'as' => 'guest-log-in', 'uses' => 'GuestController@login'
	// ]);
	$router->post('guest/info', [
		    'as' => 'guest-info', 'uses' => 'GuestController@info'
	]);
	// $router->post('guest/refresh', [
	// 	    'as' => 'guest-refresh', 'uses' => 'GuestController@refresh'
	// ]);
	// $router->post('guest/register', [
	// 	    'as' => 'guest-register', 'uses' => 'GuestController@register'
	// ]);
	// $router->post('profile/get', [
	// 	    'as' => 'profile-get', 'uses' => 'GuestController@getprofile'
	// ]);
	// $router->post('profile/set', [
	// 	    'as' => 'profile-set', 'uses' => 'GuestController@setprofile'
	// ]);
	$router->post('guest/googleplay_login', [
		    'as' => 'guest-googleplay_login', 'uses' => 'GuestController@googleplay_login'
	]);
	$router->post('guest/googleplay_logout', [
		    'as' => 'guest-info', 'uses' => 'GuestController@googleplay_logout'
	]);
	$router->post('guest/googleplay_overwrite', [
		    'as' => 'guest-googleplay_overwrite', 'uses' => 'GuestController@googleplay_overwrite'
	]);
	$router->post('guest/load_game', [
		    'as' => 'guest-load_game', 'uses' => 'GuestController@load_game'
	]);
	
	$router->post('guest/data/get', [
		    'as' => 'guest-data-get', 'uses' => 'GuestController@getData'
	]);
	$router->post('guest/data/set', [
		    'as' => 'guest-data-set', 'uses' => 'GuestController@setData'
	]);
	// $router->post('premium/get', [
	// 	    'as' => 'premium-get', 'uses' => 'PremiumController@get'
	// ]);
	// $router->post('premium/add', [
	// 	    'as' => 'premium-add', 'uses' => 'PremiumController@add'
	// ]);
	$router->post('premium/dec', [
		    'as' => 'premium-dec', 'uses' => 'PremiumController@dec'
	]);
	$router->post('premium/iap', [
		    'as' => 'premium-iap', 'uses' => 'PremiumController@iap'
	]);
	$router->post('redeem/get', [
		    'as' => 'redeem-get', 'uses' => 'RedeemController@get'
	]);
	$router->post('event/reward', [
		    'as' => 'event-reward', 'uses' => 'EventController@reward'
	]);
	$router->post('event/listing', [
		    'as' => 'event-listing', 'uses' => 'EventController@listing'
	]);
	$router->post('guest/inbox', [
		    'as' => 'guest-inbox', 'uses' => 'GuestController@inbox'
	]);
	$router->post('guest/inbox/claim', [
		    'as' => 'guest-inbox-claim', 'uses' => 'GuestController@inbox_claim'
	]);
	$router->post('guest/daily_login', [
		    'as' => 'guest-daily-login', 'uses' => 'GuestController@daily_login'
	]);
	$router->post('guest/flag', [
		    'as' => 'guest-daily-login', 'uses' => 'GuestController@flag'
	]);
});








