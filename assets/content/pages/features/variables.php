<?php

return [
    'pageTitle' => 'Features',
    'pageDescription' => 'A tour of what Freezed can do.',

    'featuresTitle' => 'Core building blocks',
    'featuresLead' => 'Each concept maps to a folder or a small piece of config.',

    'features' => [
        [
            'icon' => '▦',
            'title' => 'Content types',
            'text' => 'Top-level folders in content/ become content types, each with its own output rules in the config.',
        ],
        [
            'icon' => '▤',
            'title' => 'Pages',
            'text' => 'Every page is a folder with a Fluid template and a variables.php for its data.',
        ],
        [
            'icon' => '◧',
            'title' => 'Layouts & partials',
            'text' => 'Share structure with Fluid layouts and reusable partials across all your pages.',
        ],
        [
            'icon' => '◩',
            'title' => 'ViewHelpers',
            'text' => 'Use built-in Fluid ViewHelpers or write your own in PHP for custom logic.',
        ],
        [
            'icon' => '◪',
            'title' => 'Static files',
            'text' => 'Anything in static/ (project or theme) is copied verbatim into the build.',
        ],
        [
            'icon' => '◫',
            'title' => 'Config-driven',
            'text' => 'One freezed.config.php controls content types, default variables and build hooks.',
        ],
    ],
];
