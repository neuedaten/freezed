<?php

return [

    // Site-wide default variables. Available to every content type and every
    // page. Override them per content type (in its "variables") or per item
    // (in the item's variables.php).
    'variables' => [
        'siteName' => 'Freezed',
        'siteLanguage' => 'en',
        'currentYear' => date('Y'),
        'pageTitle' => 'Freezed site',
        'pageDescription' => 'A site built with Freezed',
        'navigation' => [
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'Features', 'url' => '/features.html'],
            ['label' => 'About', 'url' => '/about.html'],
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
