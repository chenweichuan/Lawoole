<?php

namespace Lawoole\Session;

use Session;
use Illuminate\Session\SessionServiceProvider as ServiceProvider;

class SessionServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerSessionManager();

        $this->registerSessionDriver();

        $this->app->singleton('Lawoole\Session\Middleware\StartSession');
    }
}
