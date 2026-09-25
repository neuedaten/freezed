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
        'lastmod' => null,          // fallback for pages without their own date
        'lastmodFrom' => 'lastmod', // page variable that holds the date
        'excludeWhen' => null,      // page variable that excludes when truthy, e.g. 'noindex'
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
        // Items from a class, script or JSON file instead of folders.
        'entries' => [
            'targetDirectory' => 'entries',
            'targetFileExtension' => 'html',
            'source' => \App\Content\EntrySource::class,
        ],
    ],

    // Folders templates may read files from via context="<name>".
    'assetRoots' => [
        'media' => 'data/media',
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
| `source` | string \| object | Where the items come from. Unset: the folders below `content/<type>/`. A class name implementing `ContentSourceInterface`, a path to a PHP script or JSON file (relative to the project root), or a source object. See [Content sources](content.md#content-sources). |

## `assetRoots`

Named folders that templates may read files from, in addition to the content
folder, the themes and `static/`:

```php
'assetRoots' => [
    'media' => 'data/media',
    'downloads' => 'data/downloads',
],
```

A root is addressed by its name as the `context` of
[`freezed:image`](content.md#processing-images) and
[`freezed:resource`](themes.md#assets-and-the-resource-viewhelper), with paths
relative to it:

```html
<img src="{freezed:image(src: '2026/terrace.jpg', context: 'media', width: 800)}" alt="">
<a href="{freezed:resource(path: 'brochure.pdf', context: 'downloads')}">Brochure</a>
```

Published files carry the root's name: `public/images/media/2026/terrace_….webp`
and `public/downloads/brochure.pdf` for the examples above. Choose names that
do not clash with a content type's `targetDirectory`.

Rules:

- Paths are relative to the project root and must stay inside it; `../shared`
  and absolute paths are rejected. A root may be a symlink to a folder
  elsewhere (`data/media -> ../shared-media`); a symlink *inside* a root that
  points elsewhere is refused.
- A root must not overlap `publicPath` or `imageCacheDirectory`, which the
  build empties.
- Names consist of letters, digits, `-` and `_`. `theme`, `static` and
  `content` are reserved.
- A root that does not exist fails the build.

Freezed reads files only below the project directory and these roots, see
[Where files may come from](content.md#where-files-may-come-from).

Default: `[]`.

## `siteUrl`

The public base URL of the site, e.g. `https://example.com`, without a trailing
slash (one is stripped if present). It is used wherever Freezed needs an
absolute URL:

- for the `<loc>` entries of the [sitemap](#sitemap);
- by the [`link` ViewHelper](content.md#linking-between-pages) when
  `absolute="true"` is set.

Default: `''` (not set). Relative links keep working without it.

## `assetVersioning`

Asset URLs returned by the [`resource` ViewHelper](themes.md#assets-and-the-resource-viewhelper)
carry a short hash of the file's content, so a browser fetches the new file
after a deployment instead of serving the old one from its cache:

```html
<link rel="stylesheet" href="/00_default/assets/css/main.css?v=a1b2c3d4">
```

```php
'assetVersioning' => true,   // default
```

Set it to `false` to emit plain URLs.

The version is a **content hash, not a modification time**. That matters as soon
as you build in CI: `git clone` sets the mtime of every file to the checkout
time, so an mtime-based version would invalidate every asset on every
deployment, even when nothing changed. A content hash moves only when the file
moves, which also makes builds reproducible.

The file keeps its name on disk — only the URL gains a `?v=` parameter. Relative
`url()` references inside your CSS therefore keep working; Freezed has no
bundler that could rewrite them.

See [Caching](deployment.md#caching) for the `Cache-Control` headers that go
with this.

### `assetVersioningStatic`

```php
'assetVersioningStatic' => false,   // default
```

Files from `static/` are **not** versioned by default, because `static/` exists
to deliver stable URLs (`robots.txt`, `.well-known/`, domain verification
files). Enable this to version them too — worthwhile for favicons, which
browsers cache aggressively and for a long time:

```html
<link rel="icon" href="{freezed:resource(path: 'favicon.svg', context: 'static')}" type="image/svg+xml">
```

Only takes effect while `assetVersioning` is enabled.

### Processed images

`freezed:image` is not affected by either setting. It writes the content hash
into the generated filename
(`images/pages/home/assets/hero_800x600_q80_a1b2c3d4.webp`), where it serves as
the cache key as well — see [Processing images](content.md#processing-images).

## `sitemap`

Generates `public/sitemap.xml` on every build, listing the HTML documents of
every content type.

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | `false` | Write the sitemap. `'sitemap' => true` is accepted as a shorthand. |
| `lastmod` | string \| DateTimeInterface \| null | `null` | Fallback `<lastmod>` for pages that don't define their own. Any `strtotime()`-parseable value works (e.g. `'2026-01-31'`, `date('Y-m-d')`). `null` omits the element. |
| `lastmodFrom` | string | `'lastmod'` | Name of the page variable that holds the date, e.g. `'modified'` when your pages already carry a modification date for display or structured data. |
| `excludeWhen` | string \| null | `null` | Name of a page variable whose truthy value excludes the page, e.g. `'noindex'`. Lets the sitemap follow a flag you already maintain instead of a second switch. |

Per page, in `variables.php`:

- `'lastmod' => '2026-01-31'` (or the variable named by `lastmodFrom`) sets
  the page's own `<lastmod>`, same formats as above. It wins over the config
  fallback.
- `'sitemap' => false` always excludes the page.
- `'sitemap' => true` always includes it, overriding `excludeWhen` and the
  document rule below.

Only HTML documents are listed: pages whose URL is a directory (`/cases/`),
has no extension, or ends in `.html`/`.htm`. Pages built to other files, such
as `llms.txt` or `robots.txt` via `targetFileName`, are skipped unless they set
`'sitemap' => true`.

With `excludeWhen` and `lastmodFrom` the sitemap can reuse flags your templates
already read:

```php
'sitemap' => [
    'enabled' => true,
    'lastmodFrom' => 'modified',   // the date shown as "last updated" on the page
    'excludeWhen' => 'noindex',    // the flag that renders <meta name="robots" content="noindex">
],
```

If a content item or a file in `static/` already produced
`public/sitemap.xml`, the generated sitemap overwrites it and the build logs a
warning.

URLs follow the same rule as everywhere else: a page written as `index.html`
appears as its directory URL (`https://example.com/`, `https://example.com/cases/`).

If `siteUrl` is not set, the sitemap is still written with root-relative
`<loc>` values and the build logs a warning, because search engines require
absolute URLs.

## `scripts` (build hooks)

Shell commands to run around a build. Each runs from the project root with
its output on the console. A command that exits with a non-zero status stops
the build (or the install) with an error naming the command.

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

## Directories and the file rule

A build reads only below the project directory and writes only below
`public/` and the image cache. The directories a project declares —
`contentPath`, `themesPath`, `staticPath`, `publicPath`,
`imageCacheDirectory` and every [`assetRoots`](#assetroots) entry — are
checked before any command touches the file system. A configuration that
breaks one of these rules stops with an error and nothing is read, written
or deleted:

- A declared directory is given relative to the project root and must be a
  sub-directory of it: no absolute path, no empty value, no `../shared`.
- `publicPath` and `imageCacheDirectory` are emptied by a build (or by
  `cache:flush`). Neither may be the project itself, nor contain or lie inside
  `content/`, `themes/`, `static/`, an asset root or each other. So
  `'publicPath' => ''` or `'publicPath' => 'content/pages'` are refused.
- `assetsDirectory`, `imagePublicDirectory` and a content type's
  `targetDirectory` are relative to `public/` and may only descend: no
  leading slash, no `..`. The theme sub-paths (`themeLayoutsPath`, …) keep
  their leading slash by convention but may not climb upwards either.
- A `targetFileName` in a page's variables may contain sub-folders but cannot
  leave `public/`.

Symlinks: a declared directory may be a symlink to a folder elsewhere —
`content -> ../shared-content`, `data/media -> /srv/media` — because that link
is a decision made in the project. Everything below a declared directory must
resolve inside the project or one of the declared roots. A page folder or a
theme that links elsewhere fails the build; a symlink in `static/` that points
elsewhere is skipped with a warning; a symlink inside `public/` or the cache is
removed as a link when the directory is emptied, its target is never touched.
`freezed watch` does not enter symlinked directories.

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
| `assetRoots` | `[]` | Named folders templates may read files from, see [`assetRoots`](#assetroots). |
| `assetVersioning` | `true` | Append a content hash to asset URLs, see [`assetVersioning`](#assetversioning). |
| `assetVersioningStatic` | `false` | Version files from `static/` too, see [`assetVersioningStatic`](#assetversioningstatic). |
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
