<?php
return [
    'default' => env('DATABASE.TYPE', 'mysql'),
    'connections' => [
        'mysql' => [
            'type' => env('DATABASE.TYPE', 'mysql'),
            'hostname' => env('DATABASE.HOSTNAME', '127.0.0.1'),
            'database' => env('DATABASE.DATABASE', 'kincount'),
            'username' => env('DATABASE.USERNAME', 'kincount'),
            'password' => env('DATABASE.PASSWORD', '1kincount1'),
            'hostport' => env('DATABASE.HOSTPORT', '3306'),
            'charset' => env('DATABASE.CHARSET', 'utf8mb4'),
            'debug' => env('DATABASE.DEBUG', true),
            'prefix' => '',
            'trigger_sql' => true,
        ],
    ],
];