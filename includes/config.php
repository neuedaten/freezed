<?php

return [
    'themesPath' => 'themes',
    'contentPath' => 'content',
    'publicPath' => 'public',
    'staticPath' => 'static',
    'assetsDirectory' => '',

    // Cache busting for asset URLs from freezed:resource. Appends a short hash
    // of the file's content (main.css?v=a1b2c3d4), which changes only when the
    // file changes -- unlike the modification time, which a fresh git clone in
    // CI resets on every deployment. Set to false to emit plain URLs.
    'assetVersioning' => true,

    // Also version files from static/, reachable via
    // {freezed:resource(path: 'favicon.svg', context: 'static')}. Off by
    // default, because static/ exists to deliver stable URLs (robots.txt,
    // .well-known/, domain verification files). Only takes effect while
    // assetVersioning is enabled.
    'assetVersioningStatic' => false,

    // Public base URL of the site, without a trailing slash. Used for absolute
    // URLs in the sitemap and by <freezed:link absolute="true">.
    'siteUrl' => '',

    // Sitemap (public/sitemap.xml). Lists HTML documents only.
    // "lastmod" is the fallback for items without a date of their own; null
    // omits <lastmod>. "lastmodFrom" names the item variable that holds the
    // date. "excludeWhen" names an item variable (e.g. 'noindex') whose truthy
    // value drops the item from the sitemap; null disables that rule.
    'sitemap' => [
        'enabled' => false,
        'lastmod' => null,
        'lastmodFrom' => 'lastmod',
        'excludeWhen' => null,
    ],
    // Named folders, relative to the project root, that templates may read
    // files from via context="<name>" of freezed:image and freezed:resource,
    // e.g. ['media' => 'data/media']. Roots must lie inside the project (a
    // symlink inside the project to a folder elsewhere is fine). Without an
    // entry, templates only reach the content folder, the themes and static/.
    'assetRoots' => [],

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
