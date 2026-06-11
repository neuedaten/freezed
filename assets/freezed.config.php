<?php

return [

    'contentTypes' => [
        'pages' => [
            'targetDirectory' => '',
            'targetFileExtension' => 'html',

            // Site-wide default variables. They are available in every page
            // of this content type and can be overridden per page in variables.php.
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
        ],
    ],

    // Shell commands run before ('start') and after ('end') a build.
    'scripts' => [
        'start' => [],
        'end' => [],
    ],
];
