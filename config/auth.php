<?php

use App\Modules\Identity\Models\User;

return [

    'defaults' => [
        'guard' => 'api',
        'passwords' => null,
    ],

    /*
    | O guard "api" apenas guarda o usuário autenticado na requisição. Quem
    | valida o JWT, resolve o tenant e provisiona o usuário é o middleware
    | App\Modules\Identity\Http\Middleware\AuthenticateJwt.
    */
    'guards' => [
        'api' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    'passwords' => [],

    'password_timeout' => 10800,

];
