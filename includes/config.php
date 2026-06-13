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
    'themeComponentsPath' => '/templates/components/',
    'themeStaticPath' => '/static/',
    'mkdirPermissions' => 0777,

    // Image processing (freezed:image ViewHelper).
    // Processed images are cached here (relative to the project root) so they
    // survive the clearing of public/ on every build and are only regenerated
    // when the source or parameters change.
    'imageCacheDirectory' => 'var/cache/images',
    // Output sub-folder under public/ (after assetsDirectory) for processed images.
    'imagePublicDirectory' => 'images',
    // Default encoding quality for lossy formats (jpeg, webp).
    'imageDefaultQuality' => 90,

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
