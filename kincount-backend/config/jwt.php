<?php
return [
    'algorithm' => env('JWT.ALGORITHM', 'HS256'),
    'secret' => env('JWT.SECRET', 'your-secret-key'),
    'access_ttl' => env('JWT.ACCESS_TTL', 3600 * 48),
    'refresh_ttl' => env('JWT.REFRESH_TTL', 3600 * 24 * 7),
    'auto_refresh' => env('JWT.AUTO_REFRESH', 3600 * 2),
    'issuer' => 'kincount-system',
];