<?php

namespace App\Cookie\Middleware;

use Closure;

use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse as BaseMiddleware;

class AddQueuedCookiesToResponse extends BaseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->_clearQueuedCookies();

        return parent::handle($request, $next);
    }

    /**
     * Perform any final actions for the request lifecycle.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    public function terminate($request, $response)
    {
        $this->_clearQueuedCookies();
    }

    private function _clearQueuedCookies()
    {
        foreach ($this->cookies->getQueuedCookies() as $name => $cookie) {
            $this->cookies->unqueue($name);
        }
    }
}
