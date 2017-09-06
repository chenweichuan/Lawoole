<?php

namespace Lawoole\Database;

use Lawoole\Database\Query\Monitor as QueryMonitor;
use Illuminate\Database\DatabaseServiceProvider as ServiceProvider;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        parent::register();

        $this->registerTerminating();
    }

    protected function registerTerminating()
    {
        $this->app->terminating(function() {
            foreach ($this->app['db']->getConnections() as $connection) {
                if (!$connection->getConfig('persistent')) {
                    $connection->disconnect();
                }
            }
        });
    }
}
