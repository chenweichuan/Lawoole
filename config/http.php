<?php

return [

    'server' => [
        // 监听的HOST
        'host' => env('HTTP_HOST', '127.0.0.1'),
        // 监听的端口
        'port' => env('HTTP_PORT', 5600),
        // 根目录
        'document_root' => env('DOCUMENT_ROOT', base_path('public')),
    ],

    'setting' => [
        'reactor_num' => env('HTTP_REACTOR_NUM', 16), // reactor thread num
        'worker_num' => env('HTTP_WORKER_NUM', 40), // worker process num
        'backlog' => env('HTTP_BACKLOG', 128), // listen backlog

        'max_conn' => env('HTTP_MAX_CONN', 5000),
        'max_request' => env('HTTP_MAX_REQUEST', 2000),
        'max_memory' => 134217728,

        'dispatch_mode' => env('HTTP_DISPATCH_MODE', 3),
        'heartbeat_check_interval' => 30,
        'heartbeat_idle_time' => 60,
        'open_cpu_affinity' => 1,

        'package_max_length' => 20000000,

        'daemonize' => env('HTTP_DAEMONIZE', 0),
        'log_file' => storage_path('logs') . '/http.log',
    ],

];

