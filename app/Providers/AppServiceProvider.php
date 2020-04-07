<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */

    public function register()
    {
        // $objects = new RecursiveIteratorIterator(
        //     new RecursiveDirectoryIterator('E:\xampp\htdocs\lumen\app\Http\Helpers'),
        //     RecursiveIteratorIterator::SELF_FIRST
        // );

        // foreach($objects as $name => $object){
        //     $path = $object->getRealPath();

        //     if ($object->getFileName() == "." || $object->getFileName() == "..") {
        //         continue;
        //     }

        //     if ($object->isFile()) {
        //         require_once $path;
        //     }
        // }
    }
}
