<?php

return [
    'pageTitle' => 'Home',
    'pageDescription' => 'Freezed is a static site generator powered by the TYPO3 Fluid template engine.',
    'targetFileName' => 'index.html',

    'heroEyebrow' => 'Static site generator · Beta',
    'heroTitle' => 'Static sites, powered by Fluid templates.',
    'heroSubtitle' => 'Freezed turns Fluid templates and plain content folders into a fast, dependency-free static website.',

    'featuresTitle' => 'Everything you need, nothing you don\'t',
    'featuresLead' => 'A small, focused generator built around a battle-tested template engine.',

    'features' => [
        [
            'icon' => '◆',
            'title' => 'Fluid templating',
            'text' => 'Layouts, partials, sections and ViewHelpers from the TYPO3 Fluid engine — familiar and powerful.',
        ],
        [
            'icon' => '⧉',
            'title' => 'Stackable themes',
            'text' => 'Drop themes into themes/. They layer on top of each other, so you can override anything cleanly.',
        ],
        [
            'icon' => '⚡',
            'title' => 'Plain static output',
            'text' => 'Build to a public/ folder of static HTML and assets. Host it anywhere — no runtime required.',
        ],
        [
            'icon' => '⛬',
            'title' => 'Content as folders',
            'text' => 'Each page is just a folder with a template and a variables.php. Version-control friendly and obvious.',
        ],
        [
            'icon' => '⤴',
            'title' => 'Asset pipeline',
            'text' => 'Reference CSS, JS and images with the resource ViewHelper; Freezed copies them into your build.',
        ],
        [
            'icon' => '⌘',
            'title' => 'Build hooks',
            'text' => 'Run custom scripts before and after a build via the scripts section of your config.',
        ],
    ],
];
