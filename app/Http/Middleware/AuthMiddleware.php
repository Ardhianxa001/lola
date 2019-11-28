<?php

namespace App\Http\Middleware;

use Closure;

class AuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */

    public function getUserByToken($token, $email)
    {
        date_default_timezone_set('Asia/Jakarta');
        $_token = base64_encode($email.'bitizen-x-token'.date('Y-m-d').'qwsetgd');

        if ($token != $_token) {
            throw new UnauthorizedException('Invalid Token');
        }

        $user = [
            'name' => 'Dyorg',
            'id' => 1,
            'permisssion' => 'admin'
        ];
        return $user;
    }

    public function authenticate()
    {
        $error = function($request, $response, TokenAuthentication $tokenAuth) {
            //$output = [];
            $output = [
                'msg' => $tokenAuth->getResponseMessage(),
                'token' => $tokenAuth->getResponseToken(),
                'status' => 401,
                'error' => true
            ];
            echo json_encode($output, JSON_PRETTY_PRINT);
        };

        $authenticator = function($request, TokenAuthentication $tokenAuth){
            $headers = apache_request_headers();
            if (isset($headers['User-Agent']) && isset($headers['Content-Type']) && isset($headers['User-Api-Id']) && isset($headers['X-Api-Secret'])) {
                if ($headers['X-Api-Secret'] == 'bitizen17'){
                    if ((isset($request->getParsedBody()['email'])) || ($request->getParam('email') != null) )
                    {
                        if (isset($request->getParsedBody()['email'])){

                            $email = $request->getParsedBody()['email'];
                        }elseif ($request->getParam('email') != null) {

                            $email = $request->getParam('email');
                        }else {

                            $email = 'null@gmail.com';
                        }
                        $token = $tokenAuth->findToken($request);


                        $auth = new \app\Auth();
                        $auth->getUserByToken($token, $email);
                    }
                    else {
                        if (isset($request->getParsedBody()['username'])){
                            $username = $request->getParsedBody()['username'];
                        }elseif ($request->getParam('username') != null) {
                            $username = $request->getParam('username');
                        }else{
                             $username = 'null@gmail.com';
                        }
                        $token = $tokenAuth->findToken($request);
                        $auth = new \app\Auth();
                        $auth->getUserByToken($token, $username);

                    }
                }else{
                    throw new \app\UnauthorizedException('Unauthorized');
                }

            } else {
                throw new \app\UnauthorizedException('Unauthorized');
            }
        };

        // $app->add(new TokenAuthentication([
        //     'path' =>   ['/restrict','/admin','/user', '/content',  '/comment', '/bookmark', '/profile', '/group', '/testing'],
        //     'authenticator' => $authenticator,
        //     'header' => 'X-Token-Authorization',
        //     'secure' => false,
        //     'passthrough' => ['/restrict/test'],
        //     'regex' => '/TokenBitizen\s+(.*)$/i',
        //     'error' => $error
        // ]));

        // $app->add(new \Slim\Middleware\HttpBasicAuthentication([
        //     "path" => ['/restrict','/admin','/user', '/content', '/comment', '/bookmark', '/profile', '/group', '/testing'],
        //     "passthrough" => ['/user/notificationreminder'],
        //     "realm" => "Protected",
        //     "secure" => false,
        //     "users" => [
        //         "bitizenindonesia" => "b1TiZ3N"
        //     ],
        //     "error" => function ($request, $response, $arguments) {
        //         //$output = [];
        //         $output = [
        //         'msg' => $arguments["message"],
        //         'status' => 401,
        //         'error' => true
        //         ];
        //         return $response->write(json_encode($output, JSON_UNESCAPED_SLASHES));
        //     }
        // ]));

        // // Run app
        // $app->run();
    }

    public function handle($request, Closure $next)
    {
        //Autentikasi disini
        $auth = Self::authenticate();

        return $next($request);
    }
}
