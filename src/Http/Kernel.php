<?php

namespace Lawoole\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * @var array
     */
    protected $middleware = [
        \Lawoole\View\Middleware\ResetSharedData::class,
    ];

    /**
     * The application's route middleware.
     *
     * @var array
     */
    protected $routeMiddleware = [
        // cookie
        'cookies.queue'   => \Lawoole\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        // session 
        'session.start'   => \Lawoole\Session\Middleware\StartSession::class,
    ];
}
