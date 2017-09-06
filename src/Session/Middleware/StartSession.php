<?php

namespace App\Session\Middleware;

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
use App\Session\OpenSessionHandler;

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

        $this->detectDomain($request);

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

    /**
     * Detect the session domain from the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     */
    protected function detectDomain(Request $request)
    {
        $host = $request->getHost();

        $config = $this->manager->getSessionConfig();

        // Default session domain is request host
        $domain = $host;

        // Detect specific session domain
        $domains = $config['domains'];
        $host_segments = explode('.', $host);
        for ($i = 0, $l = count($host_segments); $i < $l - 1; $i ++) {
            $_domain = implode('.', array_slice($host_segments, $i, $l - $i)); 
            if (in_array($_domain, $domains)) {
                $domain = $_domain;
                break;
            }
        }

        $this->domain = $domain;
    }

    /**
     * Start the session for the given request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Session\SessionInterface
     */
    protected function startSession(Request $request)
    {
        $session = $this->getSession($request);

        if ($session->getHandler() instanceof OpenSessionHandler) {
            $session->getHandler()->setRequest($request);
            $session->getHandler()->setDomain($this->domain);
        } else {
            $session->setRequestOnHandler($request);
        }

        $session->start();

        return $session;
    }

    /**
     * Get the session implementation from the manager.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Session\SessionInterface
     */
    public function getSession(Request $request)
    {
        $session = $this->manager->driver();

        $config = $this->manager->getSessionConfig();

        $scheme = $request->getScheme();

        if ($session->getHandler() instanceof OpenSessionHandler) {
            $channel = $request->query->get('utm_source');
            $app_id  = (strpos($channel, 'open-') === 0) ? (int) str_replace('open-', '', $channel) : null;
            $session->setName(sprintf(
                $config['cookie'] . '%u',
                crc32($scheme . '-' . $this->domain . ($app_id ? "-app:{$app_id}" : ''))
            ));
        } else {
            $session->setName(sprintf($config['cookie'] . '%u', crc32($scheme . '-' . $this->domain)));
        }

        $session->setId($request->cookies->get($session->getName()));

        return $session;
    }

    /**
     * Add the session cookie to the application response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Illuminate\Session\SessionInterface  $session
     * @return void
     */
    protected function addCookieToResponse(Response $response, SessionInterface $session)
    {
        if ($this->usingCookieSessions()) {
            $this->saveSession();
        }

        if ($this->sessionIsPersistent($config = $this->manager->getSessionConfig())) {
            if ($session->getHandler() instanceof OpenSessionHandler) {
                $request = $session->getHandler()->getRequest();
                $secure  = $request->secure() ?: Arr::get($config, 'secure', false);
            } else {
                $secure  = Arr::get($config, 'secure', false);
            }
            $response->headers->setCookie(new Cookie(
                $session->getName(), $session->getId(), $this->getCookieExpirationDate(),
                $config['path'], $this->domain, $secure
            ));
        }
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
