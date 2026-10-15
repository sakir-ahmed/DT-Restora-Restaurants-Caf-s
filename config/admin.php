<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Root Administrator
    |--------------------------------------------------------------------------
    |
    | Values come from .env (ADMIN_NAME, ADMIN_EMAIL, ADMIN_USERNAME,
    | ADMIN_PASSWORD) and are used by the root account seeder.
    |
    */

    'name' => env('ADMIN_NAME', 'Root Admin'),
    'email' => env('ADMIN_EMAIL', 'admin@dtrestora.local'),
    'username' => env('ADMIN_USERNAME', 'admin'),
    'password' => env('ADMIN_PASSWORD'),

];
