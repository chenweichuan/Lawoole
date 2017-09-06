<?php

namespace App\View\Middleware;

use Closure;
use Illuminate\Contracts\View\Factory as ViewFactory;

class ResetSharedData
{
    /**
     * The view factory implementation.
     *
     * @var \Illuminate\Contracts\View\Factory
     */
    protected $view;

    protected $keep = [
        '__env', 'app',
    ];

    /**
     * Create a new error binder instance.
     *
     * @param  \Illuminate\Contracts\View\Factory  $view
     * @return void
     */
    public function __construct(ViewFactory $view)
    {
        $this->view = $view;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $this->_resetSharedData();

        return $next($request);
    }

    /**
     * Perform any final actions for the request lifecycle.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    public function terminate($request, $response)
    {
        $this->_resetSharedData();
    }

    private function _resetSharedData()
    {
        $view = $this->view;

        foreach (array_diff(array_keys($view->getShared()), $this->keep) as $key) {
            $view->share($key, null);
        }
    }
}
