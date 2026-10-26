<?php

return [

    /*
    |--------------------------------------------------------------------------
    | JWT Signing Secret
    |--------------------------------------------------------------------------
    |
    | Secret used to sign and verify access tokens (HS256). Falls back to the
    | application key so a fresh install works out of the box, but production
    | should define a dedicated JWT_SECRET.
    |
    */

    'secret' => env('JWT_SECRET') ?: env('APP_KEY'),

    'algorithm' => 'HS256',

    /*
    |--------------------------------------------------------------------------
    | Token Lifetime
    |--------------------------------------------------------------------------
    |
    | Number of minutes an access token remains valid after being issued.
    |
    */

    'ttl' => (int) env('JWT_TTL', 60),

    'issuer' => env('APP_URL', 'http://localhost'),

];
