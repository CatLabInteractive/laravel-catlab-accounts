<?php

return [

    'catlab' => [
        'url' => env('CATLAB_API', 'https://accounts.catlab.eu/'),
        'client_id' => env('CATLAB_CLIENT_ID'),
        'client_secret' => env('CATLAB_CLIENT_SECRET'),
        'redirect'=> env('APP_URL') . '/login/callback',
        'authorizePath' => '/oauth2/authorize?reset=1'
    ]

];
