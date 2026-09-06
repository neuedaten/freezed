# Configuration

All project configuration lives in **`freezed.config.php`** at your project root.
It returns a PHP array, so you can compute values dynamically (dates, environment
variables, etc.).

## Full example

```php
<?php

return [

    // Public base URL, without a trailing slash. Used for absolute URLs in
    // the sitemap and by <freezed:link absolute="true">.
    'siteUrl' => 'https://example.com',

    // Write public/sitemap.xml on every build.
    'sitemap' => [
        'enabled' => true,
        'lastmod' => null,   // fallback for pages without their own lastmod
    ],

    // Site-wide variables, available to every content type and page.
    'variables' => [
        'siteName' => 'Freezed',
        'siteLanguage' => 'en',
        'currentYear' => date('Y'),
        'pageTitle' => 'Freezed site',
        'pageDescription' => 'A site built with Freezed',
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

## `siteUrl`

The public base URL of the site, e.g. `https://example.com`, without a trailing
slash (one is stripped if present). It is used wherever Freezed needs an
absolute URL:

- for the `<loc>` entries of the [sitemap](#sitemap);
- by the [`link` ViewHelper](content.md#linking-between-pages) when
  `absolute="true"` is set.

Default: `''` (not set). Relative links keep working without it.

## `sitemap`

Generates `public/sitemap.xml` on every build, listing every item of every
content type.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | `false` | Write the sitemap. `'sitemap' => true` is accepted as a shorthand. |
| `lastmod` | string \| DateTimeInterface \| null | `null` | Fallback `<lastmod>` for pages that don't define their own. Any `strtotime()`-parseable value works (e.g. `'2026-01-31'`, `date('Y-m-d')`). `null` omits the element. |

Per page, in `variables.php`:

- `'lastmod' => '2026-01-31'` sets the page's own `<lastmod>` (same formats as
  above). It wins over the config fallback.
- `'sitemap' => false` excludes the page from the sitemap.

URLs follow the same rule as everywhere else: a page written as `index.html`
appears as its directory URL (`https://example.com/`, `https://example.com/cases/`).

If `siteUrl` is not set, the sitemap is still written with root-relative
`<loc>` values and the build logs a warning, because search engines require
absolute URLs.

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
| `siteUrl` | `''` | Public base URL of the site, see [`siteUrl`](#siteurl). |
| `sitemap.enabled` | `false` | Write `public/sitemap.xml`, see [`sitemap`](#sitemap). |
| `sitemap.lastmod` | `null` | Fallback `<lastmod>` for the sitemap. |
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
