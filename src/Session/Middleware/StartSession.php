<?php

namespace Lawoole\Session\Middleware;

use Closure;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Session\SessionManager;
use Illuminate\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Illuminate\Session\CookieSessionHandler;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Session\Middleware\StartSession as Session;

class StartSession extends Session
{
    /**
     * The session domain.
     * 
     * @var string
     */
    protected $domain;

    /**
     * Initial session id.
     *
     * @var array
     */
    protected $initial_id;

    /**
     * Initial session data.
     *
     * @var array
     */
    protected $initial_session = [];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Flush worker session data.
        $this->manager->flush();

        $this->sessionHandled = true;

        // If a session driver has been configured, we will need to start the session here
        // so that the data is ready for an application. Note that the Laravel sessions
        // do not make use of PHP "native" sessions in any way since they are crappy.
        if ($this->sessionConfigured()) {
            $session = $this->startSession($request);

            $request->setSession($session);

            $this->initial_id      = $session->getId();
            $this->initial_session = $session->all();
        }

        $response = $next($request);

        // Again, if the session has been configured we will need to close out the session
        // so that the attributes may be persisted to some storage medium. We will also
        // add the session identifier cookie to the application response headers now.
        if ($this->sessionConfigured()) {
            $this->storeCurrentUrl($request, $session);

            $this->collectGarbage($session);

            $this->addCookieToResponse($response, $session);
        }

        return $response;
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
        if ($this->sessionHandled && $this->sessionConfigured() && ! $this->usingCookieSessions()) {
            $this->saveSession();
        }

        $this->initial_session = [];

        // Flush worker session data.
        $this->manager->flush();
    }

    protected function saveSession()
    {
        // whether session is modifiezd
        $initial_id      = $this->initial_id;
        $initial_session = $this->initial_session;
        $id      = $this->manager->driver()->getId();
        $session = $this->manager->driver()->all();
        // "_previous" is always modified, but have no sense here, so ignored
        unset($initial_session['_previous'], $session['_previous']);
        if (md5($initial_id . ':' . msgpack_pack($initial_session)) === md5($id . ':' . msgpack_pack($session))) {
            return;
        }

        $this->manager->driver()->save();
    }
}
