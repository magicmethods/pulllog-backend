<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Api Base URI
    |--------------------------------------------------------------------------
    |
    | This value is the base URI for the API, which will be used when the
    | framework needs to generate URLs for the API endpoints.
    |
    */

    'base_uri' => env('API_BASE_URI', 'beta'),

    /*
    |--------------------------------------------------------------------------
    | Api Key
    |--------------------------------------------------------------------------
    |
    | This value is the API key for authenticating requests to the API.
    |
    */

    'api_key' => env('API_KEY', ''),

];