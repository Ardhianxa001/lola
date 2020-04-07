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
	    return "LOLA BAKERY";
});

$router->group(['prefix' => '', 'middleware' => ['global']], function () use ($router) {

	//get txt for testing API
	$router->get('generatejwt', ['as' => 'profile', 'uses' => 'CommonController@generateJwt']);

	//cron x
	$router->post('get_x', ['as' => 'profile', 'uses' => 'CommonController@get_x']);
	$router->post('get_servertime', ['as' => 'profile', 'uses' => 'CommonController@get_servertime']);

	//backadmin
	$router->post('get_x_detail', ['as' => 'profile', 'uses' => 'CommonController@get_x_detail']);

	$router->get('sponsor-hit-popup', ['as' => 'sponsor-hit', 'uses' => 'EventController@hit']);
	$router->get('sponsor-hit-web', ['as' => 'sponsor-hit', 'uses' => 'EventController@hit_web']);
	$router->get('sponsor-hit-web-finish', ['as' => 'sponsor-hit', 'uses' => 'EventController@hit_web_finish']);
	$router->post('auth/login', ['as' => 'profile', 'uses' => 'AuthController@login']);
	$router->post('servertime', ['as' => 'servertime', 'uses' => 'CommonController@servertime']);
});

$router->group(['prefix' => '', 'middleware' => ['global','jwt','pvt']], function () use ($router) {
	$router->post('guest/info', [
		    'as' => 'guest-info', 'uses' => 'GuestController@info'
	]);
	$router->post('guest/data/get', [
		    'as' => 'guest-data-get', 'uses' => 'GuestController@getData'
	]);
	$router->post('guest/data/set', [
		    'as' => 'guest-data-set', 'uses' => 'GuestController@setData'
	]);
	$router->post('guest/daily_login', [
		    'as' => 'guest-daily-login', 'uses' => 'GuestController@daily_login'
	]);
	$router->post('landmark/iap', [
		    'as' => 'landmark-iap', 'uses' => 'LandmarkController@iap'
	]);
	$router->post('landmark/list', [
		    'as' => 'landmark-list', 'uses' => 'LandmarkController@list'
	]);
	$router->post('guest/googleplay_login', [
		    'as' => 'guest-googleplay_login', 'uses' => 'GoogleController@googleplay_login'
	]);
	$router->post('guest/googleplay_logout', [
		    'as' => 'guest-info', 'uses' => 'GoogleController@googleplay_logout'
	]);
	$router->post('guest/googleplay_overwrite', [
		    'as' => 'guest-googleplay_overwrite', 'uses' => 'GoogleController@googleplay_overwrite'
	]);
	$router->post('guest/load_game', [
		    'as' => 'guest-load_game', 'uses' => 'GoogleController@load_game'
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
	$router->post('sponsor', [
		    'as' => 'sponsor-building', 'uses' => 'EventController@sponsor'
	]);
	$router->post('guest/inbox', [
		    'as' => 'guest-inbox', 'uses' => 'InboxController@inbox'
	]);
	$router->post('guest/inbox/claim', [
		    'as' => 'guest-inbox-claim', 'uses' => 'InboxController@inbox_claim'
	]);
	$router->post('adsense', [
		    'as' => 'adsense', 'uses' => 'AdsenseController@index'
	]);
});