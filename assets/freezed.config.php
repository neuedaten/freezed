<?php

return [

    // Public base URL of the site, without a trailing slash. Needed for
    // absolute URLs in the sitemap and for <freezed:link absolute="true">.
    // 'siteUrl' => 'https://example.com',

    // Generate public/sitemap.xml on every build. Items can opt out with
    // 'sitemap' => false and set their own 'lastmod' in variables.php.
    // 'sitemap' => [
    //     'enabled' => true,
    //     'lastmod' => null,          // fallback, e.g. '2026-01-31' or date('Y-m-d')
    //     'lastmodFrom' => 'lastmod', // item variable holding the date, e.g. 'modified'
    //     'excludeWhen' => null,      // item variable that excludes when truthy, e.g. 'noindex'
    // ],

    // Asset URLs from freezed:resource carry a hash of the file's content
    // (main.css?v=a1b2c3d4) so browsers pick up changed assets. Set to false
    // for plain URLs.
    // 'assetVersioning' => false,

    // Version files from static/ too, referenced via
    // {freezed:resource(path: 'favicon.svg', context: 'static')}. Off by
    // default, because static/ is meant to deliver stable URLs.
    // 'assetVersioningStatic' => true,

    // Site-wide default variables. Available to every content type and every
    // page. Override them per content type (in its "variables") or per item
    // (in the item's variables.php).
    'variables' => [
        'siteName' => 'Freezed',
        'siteLanguage' => 'en',
        'currentYear' => date('Y'),
        'pageTitle' => 'Freezed site',
        'pageDescription' => 'A site built with Freezed',
        // Rendered with <freezed:link href="{item.href}">. A CONTENT: reference
        // resolves to the page's public URL at build time.
        'navigation' => [
            ['label' => 'Home', 'href' => 'CONTENT:pages/home'],
            ['label' => 'Features', 'href' => 'CONTENT:pages/features'],
            ['label' => 'About', 'href' => 'CONTENT:pages/about'],
        ],
    ],

    'contentTypes' => [
        'pages' => [
            'targetDirectory' => '',
            'targetFileExtension' => 'html',
        ],
    ],

    // Shell commands run before ('start') and after ('end') a build.
    'scripts' => [
        'start' => [],
        'end' => [],
    ],
];
