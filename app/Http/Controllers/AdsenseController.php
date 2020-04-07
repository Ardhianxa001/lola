<?php

namespace App\Http\Controllers;

use Helpers;
use Validator;

use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;
use App\Http\Models\Adsense;
use Log;
//use App\Http\Models\Guest;

class AdsenseController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    
    public function index(Request $request)
    {   
        //credential yang sudah di filter oleh middleware
        $credentials = $request->get('claim');
        $game_id = $credentials->game_id;
        $guest_id = $credentials->guest_id;
        $uid = $credentials->uid;
        $signature = $credentials->signature;

        //cek signature
        $checkSignature = CommonController::checkSignature($signature,$request->segments(),'',$guest_id,$game_id,$uid);
        if($signature != $checkSignature['checkSignature']){
            Log::channel('error')->error($guest_id.' | adsense | -403 Wrong Signature!');
            return response()->json([
                'code' => -403,
                'message' => 'Wrong Signature!',
            ]);
        }

        //guest block di non aktifkan karena belum digunakan oleh client
        //$guest = Guest::select('block')->where('id',$guest_id)->where('origin_game_id',2)->first();
        $adsense = Adsense::select('id','slot','platform')->where('status',1)->get();

        //create signature for client
        $varResponse = "";
        $signatureResponse = CommonController::generateSignature($varResponse,$checkSignature['signatureName'],$guest_id,$game_id,$uid);

        //create payload for client
        $payload = array(
            'game_id' => $game_id,
            'guest_id' => $guest_id,
            //'block' => $guest->block,
            'adsense' => json_encode($adsense),
            'signature' => $signatureResponse,
            'code' => 200,
            'x' => $credentials->x
        );
        $message = 'Get Data Adsense Slot Success!';

        return Helpers::response($payload,$message,$request->get('secret_key'),$request->get('sess_id'));

    }
}