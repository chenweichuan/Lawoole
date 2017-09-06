<?php

require __DIR__.'/../bootstrap/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$app->bootstrapWith([
    'Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables',
    'Illuminate\Foundation\Bootstrap\LoadConfiguration',
    'Illuminate\Foundation\Bootstrap\HandleExceptions',
    'Illuminate\Foundation\Bootstrap\RegisterFacades',
    'Illuminate\Foundation\Bootstrap\SetRequestForConsole',
    'Illuminate\Foundation\Bootstrap\RegisterProviders',
    'Illuminate\Foundation\Bootstrap\BootProviders',
]);

\App::environment('production') && error_reporting(0);

$GLOBALS['_DEFAULT_SERVER'] = $_SERVER;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$input = new Symfony\Component\Console\Input\ArgvInput;
$input->getParameterOption('-h') && config(['http.server.host' => $input->getParameterOption('-h')]);
$input->getParameterOption('-p') && config(['http.server.port' => $input->getParameterOption('-p')]);

$config = config('http');
$http = new swoole_http_server($config['server']['host'], $config['server']['port']);
$http->set($config['setting']);

$http->on('request', function($request, $response) use($kernel, $config)
{
    // Super Global
    $_SERVER = $GLOBALS['_DEFAULT_SERVER'];
    foreach ($request->header as $k => $v) {
        $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
    }
    foreach ($request->server as $k => $v) {
        $_SERVER[strtoupper($k)] = $v;
    }
    $_SERVER['DOCUMENT_ROOT'] = $config['server']['document_root'];
    $_GET     = isset($request->get) ? $request->get : [];
    $_POST    = isset($request->post) ? $request->post : [];
    $_COOKIE  = isset($request->cookie) ? $request->cookie : [];
    $_FILES   = isset($request->files) ? $request->files : [];
    $_REQUEST = array_merge($_GET, $_POST);
    $GLOBALS['HTTP_RAW_POST_DATA'] = $request->rawContent();

    // Framework
    $illuminate_response = $kernel->handle(
        $illuminate_request = Illuminate\Http\Request::capture()
    );

    // Protocol
    $response->header(sprintf('HTTP/%s %s %s'
        , $illuminate_response->getProtocolVersion()
        , $illuminate_response->getStatusCode()
        , isset($illuminate_response::$statusTexts[$illuminate_response->getStatusCode()]) ? $illuminate_response::$statusTexts[$illuminate_response->getStatusCode()] : ''
    ), '');
    // Headers
    foreach ($illuminate_response->headers->allPreserveCase() as $name => $values) {
        foreach ($values as $value) {
            $response->header($name, $value);
        }
    }
    // Cookies
    foreach ($illuminate_response->headers->getCookies() as $cookie) {
        $response->cookie($cookie->getName(), $cookie->getValue(), $cookie->getExpiresTime(), $cookie->getPath(), $cookie->getDomain(), $cookie->isSecure(), $cookie->isHttpOnly());
    }
    // Status 
    $response->status($illuminate_response->getStatusCode());
    // Content
    $content = $illuminate_response->getContent();
    // End
    $kernel->terminate($illuminate_request, $illuminate_response);
    $response->end($content);

    // Memory
    !empty($config['setting']['max_memory']) && (memory_get_usage() >= $config['setting']['max_memory']) && exit;
});

$http->start();
