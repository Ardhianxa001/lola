<?php
namespace App\Http\Middleware;

use Closure;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;

class JwtBearerMiddleware
{
    public function handle($request, Closure $next, $guard = null)
    {
        $token = $request->bearerToken();
        
        if(!$token) {
            // Unauthorized response if token not there
            return [
                'code' => 401,
                'error' => 'Token not provided.'
            ];
        }
        try {
            $credentials = JWT::decode($token, config('key.secret_jwt'), ['HS256']);
        } catch(ExpiredException $e) {
            return [
                'code' => 400,
                'error' => 'Provided token is expired.'
            ];
        } catch(\Exception $e) {
            return [
                'code' => 400,
                'error' => 'An error while decoding token.'
            ];
        }
    
        
        //$request->request->add(['auth' => ['user'=>$credentials->user, 'desc'=>$credentials->desc]]);
        return $next($request);
    }
}