<?php
namespace App\Http\Middleware;

use Closure;
use Exception;
use App\User;
use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;

class LogMiddleware
{

    public function __construct()
    {
        
    }

    public function handle($request, Closure $next, $guard = null)
    {
        //writeLogBackend(env('APP_BASE_URL'),date('Y-m-d H:i:s'),get_client_ip(),cekMobile(),(isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]");

        return $next($request);
    }
}
?>