<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Route;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function callAction($method, $parameters)
{
    $logOnly = ['store', 'update', 'destroy'];

    if (auth()->check() && in_array($method, $logOnly)) {
        activity()
            ->causedBy(auth()->user())
            ->withProperties([
                'controller' => class_basename(static::class),
                'action' => $method,
                'route' => \Route::currentRouteName(),
                'parameters' => $parameters,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ])
            ->log("User performed {$method}");
    }

    return parent::callAction($method, $parameters);
}

}
