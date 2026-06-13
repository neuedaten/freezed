# Configuration

All project configuration lives in **`freezed.config.php`** at your project root.
It returns a PHP array, so you can compute values dynamically (dates, environment
variables, etc.).

## Full example

```php
<?php

return [

    // Site-wide variables, available to every content type and page.
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
        'cases' => [
            'targetDirectory' => 'cases',
            'targetFileExtension' => 'html',
            // Overrides the site-wide variables for this content type only.
            'variables' => [
                'pageTitle' => 'Case study',
            ],
        ],
    ],

    'scripts' => [
        'start' => [],
        'end' => [],
    ],
];
```

## `variables` (site-wide)

Top-level default variables available to **every** content type and every page.
This is the place for things like `siteName`, `currentYear` or `navigation` that
the whole site shares.

Variables are merged in this order (later wins):

1. `variables` — site-wide defaults (this key).
2. `contentTypes.<type>.variables` — per content type.
3. The item's `variables.php` — per page.

So a single page can override a site-wide default, and a content type can set
defaults that differ from the rest of the site, all while inheriting everything
else.

## `contentTypes`

A map of content type slug → configuration. The slug must match a folder name in
`content/`.

| Key | Type | Description |
|-----|------|-------------|
| `targetDirectory` | string | Output sub-folder under `public/` (`''` = root). |
| `targetFileExtension` | string | Default extension for generated files (e.g. `html`). |
| `variables` | array | Variables for every page of this type. Override the site-wide [`variables`](#variables-site-wide); overridden per page. |

## `scripts` (build hooks)

Shell commands to run around a build. Each runs from the project root.

| Event | When it runs |
|-------|--------------|
| `start` | Before the build begins. |
| `end` | After the build completes. |

```php
'scripts' => [
    'start' => [
        'npm run build:css',
    ],
    'end' => [
        'echo "Build finished"',
    ],
],
```

The `install` command additionally supports `beforeInstall` and `afterInstall`
events.

## Built-in path defaults

Freezed ships with sensible defaults (defined in the engine's
`includes/config.php`). You normally don't need to change these, but they can be
overridden in `freezed.config.php`:

| Key | Default | Meaning |
|-----|---------|---------|
| `themesPath` | `themes` | Folder containing themes. |
| `contentPath` | `content` | Folder containing content. |
| `publicPath` | `public` | Build output folder. |
| `staticPath` | `static` | Project-level static files. |
| `assetsDirectory` | `''` | Sub-path under `public/` for copied resources. |
| `themeTemplatesPath` | `/templates/templates/` | Templates folder within a theme. |
| `themeLayoutsPath` | `/templates/layouts/` | Layouts folder within a theme. |
| `themePartialsPath` | `/templates/partials/` | Partials folder within a theme. |
| `themeStaticPath` | `/static/` | Static folder within a theme. |
| `mkdirPermissions` | `0777` | Permissions for created directories. |
| `imageCacheDirectory` | `var/cache/images` | Where processed images are cached (relative to project root). |
| `imagePublicDirectory` | `images` | Output sub-folder under `public/` for processed images. |
| `imageDefaultQuality` | `90` | Default encoding quality for lossy image formats. |

## Environment

| Variable | Effect |
|----------|--------|
| `FREEZED_ROOT` | Forces the project root, overriding auto-detection. |
