<?php
return [
    'settings' => [
        'displayErrorDetails' => true, // set to false in production
        'addContentLengthHeader' => false, // Allow the web server to send the content-length header

        // Renderer settings
        'renderer' => [
            'template_path' => __DIR__ . '/../templates/',
            'cache_path' => __DIR__ . '/../cache/'
        ],

        // Monolog settings
        'logger' => [
            'name' => 'pxlsAdmin',
            'path' => __DIR__ . '/../logs/app.log',
            'level' => \Monolog\Logger::DEBUG
        ],

        'discord' => [
            'name'   => "discord webhook name",
            'url'   => "discord webhook URL"
        ],

        'db' => [
            'host' => 'localhost',
            'user' => 'pxls',
            'pass' => 'password',
            'dbname' => 'pxls'
        ],

        //tokens.bypass bypasses some auth on various endpoints and will be expanded on at some point
        //useful for cronjobs/bots/etc and is the only way to trigger some endpoints (such as Report's Announce)
        'tokens' => [
            'bypass' => ''
        ],

        //Root URLs for various XHR requests and links. No trailing slash
        'webroots' => [
            'panel' => 'https://admin.pxls.space', //The admin panel's root
            'game' => 'https://pxls.space' //The game's root (where the canvas resides)
        ]
    ],
];
