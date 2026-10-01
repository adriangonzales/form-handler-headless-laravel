<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | The load balancers or reverse proxies in front of the application, as a
    | comma-separated list of IPs or CIDR ranges, or "*" to trust the calling
    | proxy. Their X-Forwarded-* headers set the request's scheme and host,
    | which absolute URLs such as export download links are built from.
    | Laravel Cloud, Forge and Vapor hosts are trusted automatically.
    |
    */

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
