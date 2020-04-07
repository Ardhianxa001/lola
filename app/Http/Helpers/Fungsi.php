<?php
function version()
{
  $prefix = '';
  $v = "1.0.0";
  if(isset($_GET['v'])){
    $v = $_GET['v'];
  }
  
  $v_ex = explode(".",$v);
  if(isset($v_ex[0]) && isset($v_ex[1]) &&isset($v_ex[2])){
    $v_1 = $v_ex[0];
    $v_2 = $v_ex[1];
    $v_3 = $v_ex[2];

    $v_int = ($v_1*1000) + ($v_2*100) + ($v_3*10);
    if($v_int >= 1060){
      //minimal version 1.0.6
      $prefix = "v1a\\";
    }
    else{
      $prefix = "v1\\";
    }
  }
    
  return $prefix;
}

function ssl_decrypt($txt,$chiper_aes,$token,$iv, $tag, $sess_id)
{
    return openssl_decrypt($txt, $chiper_aes, base64_decode($token), $options=0, $iv, $tag, $sess_id);
}

function ssl_encrypt($jwt,$token,$sess_id,$chiper_aes)
{   
    $ivlen = openssl_cipher_iv_length($chiper_aes);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $iv_base64 = base64_encode($iv);
    $ciphertext = openssl_encrypt($jwt, $chiper_aes, base64_decode($token), $options=0, $iv, $tag, $sess_id);
    
    return $ciphertext.'.'.$iv_base64.'.'.base64_encode($tag).'.'.$sess_id;
}

function gmt_7($datetime)
{
    return gmdate('Y-m-d H:i:s',$datetime + (60 * 60 * 7));
}

function isJson($string) {
    json_decode($string);
    return (json_last_error() == JSON_ERROR_NONE);
}

function curl($method,$url,$headers,$fields)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);  
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields,JSON_PRETTY_PRINT));
    $result = curl_exec($ch);           
    if ($result === FALSE) {
        die('Curl failed: ' . curl_error($ch));
    }
    curl_close($ch);

    echo $result;
}

function validateArray($rules, $array)
{
    $errors = [];

    foreach ($rules as $key => $value) {
        if ($rules[$key] == 'required') {
            if (!isset($array[$key])) {
                array_push($errors, $key.' is required');
            }elseif($array[$key] == null){
                array_push($errors, $key.' is required');
            }elseif($array[$key] == false){
                array_push($errors, $key.' is required');
            }
        }
    }

    if (count($errors) != 0) {
        $response = (object)[
            'error' => true,
            'errors' => $errors,
        ];
    }else{
        $response = (object)[
            'error' => false,
            'errors' => $errors,
        ];
    }

    return $response;
}

function cekMobile()
{
    $device = explode("(",$_SERVER['HTTP_USER_AGENT']);
    if(isset($device[1])){
        $device2 = explode(")",$device[1]);
        if(isset($device2[0])){
            $device = $device2[0];
        }
        else{
            $device = $_SERVER['HTTP_USER_AGENT'];
        }
    }
    else{
        $device = $_SERVER['HTTP_USER_AGENT'];
    }

    return $device;
}

function get_client_ip() {
  $ipaddress = '';
  if (isset($_SERVER['HTTP_CLIENT_IP']))
      $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
  else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
      $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
  else if(isset($_SERVER['HTTP_X_FORWARDED']))
      $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
  else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
      $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
  else if(isset($_SERVER['HTTP_FORWARDED']))
      $ipaddress = $_SERVER['HTTP_FORWARDED'];
  else if(isset($_SERVER['REMOTE_ADDR']))
      $ipaddress = $_SERVER['REMOTE_ADDR'];
  else
      $ipaddress = 'UNKNOWN';
  return $ipaddress;
}

