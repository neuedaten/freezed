<?php

return [
    'themesPath' => 'themes',
    'contentPath' => 'content',
    'publicPath' => 'public',
    'staticPath' => 'static',
    'assetsDirectory' => '',
    'themeTemplatesPath' => '/templates/templates/',
    'themeLayoutsPath' => '/templates/layouts/',
    'themePartialsPath' => '/templates/partials/',
    'themeStaticPath' => '/static/',
    'mkdirPermissions' => 0777,

    // Built-in web server (freezed serve / run). Overridable via --host / --port.
    'serve' => [
        'host' => 'localhost',
        'port' => 8080,
    ],

    // File watcher (freezed watch / run). Polling interval in milliseconds.
    'watch' => [
        'intervalMs' => 500,
    ],
];
