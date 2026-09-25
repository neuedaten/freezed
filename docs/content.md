# Content & pages

This guide covers how to add and structure the content of your site.

## Anatomy of a page

A page is a folder inside a content type. The default `pages` type lives in
`content/pages/`:

```text
content/pages/about/
├─ index.html      # Fluid template
└─ variables.php   # variables for this page
```

### `index.html`

The template selects a layout and fills its sections:

```html
<f:layout name="page" />

<f:section name="content">
    <section class="section">
        <div class="container prose">
            <h1>{pageTitle}</h1>
            <p>Hello from the about page.</p>
        </div>
    </section>
</f:section>
```

- `<f:layout name="page" />` uses the theme's `layouts/page.html`.
- `<f:section name="content">` provides the content the layout renders via
  `<f:render section="content" />`.

> **Layout names are case-sensitive on Linux.** Use `name="page"` to match
> `page.html`. (`name="Page"` will fail on case-sensitive filesystems.)

### `variables.php`

Returns an associative array. These keys become template variables:

```php
<?php

return [
    'pageTitle' => 'About',
    'pageDescription' => 'What this site is about.',
];
```

Reference them in the template with `{pageTitle}`, `{pageDescription}`, etc.

## Output filenames

By default the folder name plus the content type's `targetFileExtension`
determines the output file:

| Page folder | Output |
|-------------|--------|
| `content/pages/home` | `home.html` |
| `content/pages/about` | `about.html` |

Override the filename with a `targetFileName` variable — for example, to make
the home page the site index:

```php
return [
    'pageTitle' => 'Home',
    'targetFileName' => 'index.html',
];
```

`targetFileName` may contain a path. The sub-folders are created below the
content type's `targetDirectory` at build time, so
`'targetFileName' => 'guides/first-steps/index.html'` writes
`public/guides/first-steps/index.html`. It cannot leave `public/`: a value
that climbs upwards (`../../index.html`) fails the build with the item's name.

### Public URLs

Wherever Freezed derives a page's URL — the [`link` ViewHelper](#linking-between-pages),
the `url` key of [`contentTypeCollection`](#listing-items-of-a-content-type) and
the [sitemap](configuration.md#sitemap) — it uses the content type's
`targetDirectory` plus the output filename. A file named `index.<ext>` collapses
to its directory:

| Output file | URL |
|-------------|-----|
| `public/about.html` | `/about.html` |
| `public/index.html` | `/` |
| `public/cases/first-case.html` | `/cases/first-case.html` |
| `public/cases/index.html` | `/cases/` |
| `public/cases/first/index.html` | `/cases/first/` |

## Default and per-page variables

Variables come from three places and are merged in this order (later wins):

1. **Site-wide** — the top-level `variables` in `freezed.config.php`, shared by
   every content type and page (e.g. `siteName`, `currentYear`, `navigation`).
2. **Per content type** — `contentTypes.<type>.variables`, overriding the
   site-wide defaults for that type only.
3. **Per page** — the page's `variables.php`, overriding both.

```php
// freezed.config.php
'variables' => [
    'siteName' => 'My Site',
    'navigation' => [
        ['label' => 'Home', 'href' => 'CONTENT:pages/home'],
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
        'variables' => [
            'pageTitle' => 'Case study',   // default for all cases
        ],
    ],
],
```

So a page's `variables.php` is merged **on top** of the content type's defaults,
which are merged on top of the site-wide defaults — each level can override a
value while still inheriting the rest.

## Passing data to partials

Use Fluid's `<f:render partial>` with `arguments`:

```html
<f:render partial="hero" arguments="{
    title: heroTitle,
    subtitle: heroSubtitle
}" />
```

Here `heroTitle` and `heroSubtitle` come from the page's `variables.php`, and the
`hero` partial reads `{title}` and `{subtitle}`.

## Looping over data

Variables can be arrays of arrays — ideal for lists, cards or navigation:

```php
'features' => [
    ['title' => 'Fast', 'text' => 'Static output.'],
    ['title' => 'Simple', 'text' => 'Just folders.'],
],
```

```html
<f:for each="{features}" as="feature">
    <h3>{feature.title}</h3>
    <p>{feature.text}</p>
</f:for>
```

## Linking between pages

The `link` ViewHelper renders an `<a>` tag, much like TYPO3's `f:link`. Its
`href` accepts three kinds of targets:

```html
{namespace freezed=Neuedaten\Freezed\ViewHelpers}

<!-- a content reference: CONTENT:<contentType>/<pageFolder> -->
<freezed:link href="CONTENT:pages/impressum" class="footer__link">Imprint</freezed:link>

<!-- an absolute URL -->
<freezed:link href="https://example.org" target="_blank">External</freezed:link>

<!-- a relative or root-relative URL, passed through unchanged -->
<freezed:link href="/downloads/brochure.pdf">Brochure</freezed:link>
```

A `CONTENT:` reference names a content type and a page folder. At build time it
is resolved to the page's [public URL](#public-urls), so links keep working when
you rename an output file, move a content type to another `targetDirectory` or
turn a page into a directory index. The scaffold's navigation uses this:

```php
'navigation' => [
    ['label' => 'Home', 'href' => 'CONTENT:pages/home'],
    ['label' => 'About', 'href' => 'CONTENT:pages/about'],
],
```

```html
<f:for each="{navigation}" as="item">
    <freezed:link class="nav__link" href="{item.href}">{item.label}</freezed:link>
</f:for>
```

### Arguments

| Argument | Required | Default | Description |
|----------|----------|---------|-------------|
| `href` | yes | — | Absolute URL, relative URL or `CONTENT:<type>/<folder>` reference. |
| `section` | no | — | Anchor appended as `#section`. |
| `absolute` | no | `false` | Prefix root-relative URLs with [`siteUrl`](configuration.md#siteurl). Absolute URLs are never changed. |

Every other attribute — `class`, `id`, `title`, `target`, `rel`, `download`,
`data-*`, `aria-*`, … — is passed through to the tag as-is. A link with
`target="_blank"` and no explicit `rel` gets `rel="noopener"`.

### Dead links

If a `CONTENT:` reference doesn't match any page at build time — the content
type or the folder doesn't exist, or the reference is malformed — no link is
created. The same happens for a URL with a scheme other than `http`, `https`,
`mailto`, `tel`, `sms` or `ftp`: `javascript:` and `data:` URLs become dead
links, so a value that arrives through a [content source](#content-sources)
cannot inject script into a page. Relative, root-relative and `//host` URLs
are unaffected. Freezed renders a `<span class="dead-link">` with the same content and
attributes instead (link-only attributes such as `target` and `rel` are dropped,
`dead-link` is prepended to your `class`), and logs a warning naming the
reference and the page it was first seen in. The build still succeeds.

```html
<freezed:link href="CONTENT:pages/nope" class="btn">Missing</freezed:link>
<!-- renders as -->
<span class="dead-link btn">Missing</span>
```

Style `.dead-link` in your theme to make broken references visible, or leave it
unstyled so the text simply appears without a link.

## Processing images

The `image` ViewHelper resizes, converts and re-encodes an image at build time
and returns the public path of the generated file — a scaled-down version of
TYPO3's `f:image`:

```html
{namespace freezed=Neuedaten\Freezed\ViewHelpers}

<img src="{freezed:image(
    src: 'assets/images/hero.jpg',
    context: 'theme',
    width: 800,
    fileType: 'webp',
    quality: 80
)}" alt="">
```

| Argument | Default | Description |
|----------|---------|-------------|
| `src` | — | Path to the source image, relative to the context's root (resolved like `freezed:resource`). |
| `context` | (template root) | `theme` resolves from the theme template roots, `static` from the `static/` directories, the name of an [`assetRoots`](configuration.md#assetroots) entry from that folder; empty resolves relative to the content template. |
| `width` | `auto` | Target width in px, or `auto`. |
| `height` | `auto` | Target height in px, or `auto`. |
| `fileType` | source type | Output format: `jpg`, `png`, `webp`, `gif`. |
| `quality` | `imageDefaultQuality` (90) | Encoding quality for lossy formats (jpeg, webp). |
| `scaleUp` | `false` | Allow enlarging beyond the original size. |

The aspect ratio is always preserved: give one of `width`/`height` to scale by
that side, or both to fit the image inside that box. With `scaleUp` left at
`false` the image is never enlarged past its original dimensions.

Generated files mirror the source's path below `content/`, `themes/` or the
asset root — the same sub-folders `freezed:resource` uses — followed by the
original name, target resolution, quality and a short hash of the source
content:

| Source | Output |
|--------|--------|
| `content/pages/home/assets/hero.jpg` | `public/images/pages/home/assets/hero_800x600_q80_a1b2c3d4.webp` |
| `content/news/launch/assets/hero.jpg` | `public/images/news/launch/assets/hero_800x600_q80_e5f6a7b8.webp` |
| `themes/00_default/assets/images/hero.jpg` | `public/images/00_default/assets/images/hero_800x600_q80_c9d0e1f2.webp` |
| `data/media/2026/terrace.jpg` via `context="media"` | `public/images/media/2026/terrace_800x600_q80_d3e4f5a6.webp` |

A `hero.jpg` in one content folder therefore never collides with a `hero.jpg`
in another. Everything that affects the result is part of the name (`scaleUp`
needs no part of its own, because it can only change the output by changing the
dimensions), so replacing the source image or changing `quality` produces a new
file rather than reusing the old one — and the new URL busts the browser cache
at the same time.

Files are cached in `var/cache/images/` (in the same sub-folder structure),
generated once, reused on later builds and copied into `public/images/` on each
build. Imagick is used when available, otherwise GD; source types that can't be
decoded (e.g. SVG) are passed through unchanged — they get the content hash too.

An SVG that contains a `<script>` element, an event handler attribute
(`onload="…"`), a `javascript:` URL or a `<foreignObject>` is not published,
by `freezed:image` or by `freezed:resource`; the build logs a warning and
renders an empty path instead. An SVG served from the site's own origin runs
with the site's rights, so this keeps a file that arrived through an upload
or a [content source](#content-sources) from turning into a script on your
domain. It is a pattern check for the plain cases, not a sanitiser: keep
untrusted SVGs out of your data where you can.

Because the filename is the cache key, image URLs are always versioned,
independently of [`assetVersioning`](configuration.md#assetversioning). That
also makes `public/images/` safe for a long `Cache-Control: immutable`, see
[Caching](deployment.md#caching).

Superseded files stay in `var/cache/images/`; they are never published, they
just take up disk space. Run `./vendor/bin/freezed cache:flush` to clear them
out.

### Where files may come from

A build only reads files below the project directory, its content, theme and
static directories and the configured [`assetRoots`](configuration.md#assetroots),
and only writes below `public/` and the image cache. `src`/`path` is always
relative to its context's root: an absolute path (`/var/…`) fails the build,
and so does a relative path that resolves to a place outside those roots
(`../../../etc/hosts`). A `..` that stays inside the project is fine. The rule
keeps a stray path — a typo, or a value that arrived through a
[content source](#content-sources) — from copying arbitrary files of the build
machine into `public/`.

Symlinks follow one rule: a directory declared in `freezed.config.php`
(`contentPath`, `themesPath`, `staticPath`, `publicPath`,
`imageCacheDirectory`, an `assetRoots` entry) may be a symlink to a folder
elsewhere — that link is a decision made in the project. Anything *below* such
a directory must resolve inside the project or one of those roots: a page
folder, a theme or a file in `static/` that links to somewhere else fails the
build (static files are skipped with a warning). See
[Configuration › Directories and the file rule](configuration.md#directories-and-the-file-rule).

To use images or downloads that live outside `content/` and `themes/`, give
their folder a name in `assetRoots` and address it as `context`:

```php
// freezed.config.php
'assetRoots' => [
    'media' => 'data/media',
],
```

```html
<img src="{freezed:image(src: '2026/terrace.jpg', context: 'media', width: 800)}" alt="">
<a href="{freezed:resource(path: 'brochure.pdf', context: 'media')}">Brochure</a>
```

The folder must lie inside the project. It may be a symlink to a folder
elsewhere (`data/media -> ../shared-media`) — that link is the deliberate
decision, made in the project, that the folder belongs to the site. Files are
still checked against the linked folder, so a path cannot leave it, and a
symlink inside the folder that points elsewhere is refused.

## Adding a new content type

To add, say, a blog:

1. Create `content/posts/` and a page folder inside it,
   e.g. `content/posts/hello-world/`.
2. Add a `posts` entry to `freezed.config.php`:

   ```php
   'posts' => [
       'targetDirectory' => 'blog',     // output under public/blog/
       'targetFileExtension' => 'html',
       'variables' => [],
   ],
   ```

3. Build. Pages render to `public/blog/hello-world.html`.

> Every content type folder in `content/` **must** have a matching config entry,
> or the build will stop with an error.

## Content sources

By default the items of a content type are the folders below
`content/<type>/`. A content type can instead take its items from a PHP class,
a PHP script or a JSON file — a database, an API export, a spreadsheet, whatever
you have — while everything else stays the same: variables, output files,
`CONTENT:` links, `contentTypeCollection`, the sitemap. Set `source` on the
content type:

```php
'contentTypes' => [
    'pages' => [                              // no "source": folders, as before
        'targetDirectory' => '',
        'targetFileExtension' => 'html',
    ],
    'entries' => [
        'targetDirectory' => 'entries',
        'targetFileExtension' => 'html',
        'source' => \App\Content\EntrySource::class,
    ],
    'spots' => [
        'targetDirectory' => 'spots',
        'targetFileExtension' => 'html',
        'source' => \App\Content\EntrySource::class,   // same class, shared instance
    ],
    'search' => [
        'targetDirectory' => '',
        'targetFileExtension' => 'json',
        'source' => 'data/search.php',
    ],
],
```

The folder `content/<type>/` still exists — it is what makes the type known —
but for a sourced type it holds only the templates and shared assets:

```text
content/entries/
├─ index.html        # default template for every item
├─ restaurant.html   # used by items that name it (see "template" below)
└─ assets/           # shared assets, resolved relative to this folder
```

`source` accepts:

| Value | Meaning |
|-------|---------|
| *(none)* or `'directory'` | The folders below `content/<type>/` — the default. |
| `\App\Content\EntrySource::class` | A class implementing `Neuedaten\Freezed\Domain\Source\ContentSourceInterface`, autoloaded through your project's `composer.json`. One instance serves every content type that names the class, so types can share a database connection. |
| `'data/entries.php'` | A PHP script, relative to the project root. It is included like a `variables.php` and returns the items; `$typeSlug` and `$contentTypeConfig` are in scope. The script may also return a source object instead of an array. |
| `'data/entries.json'` | A JSON file, relative to the project root, holding a list of items — or an object keyed by content type slug (`{"entries": […], "spots": […]}`) so one file can feed several types. The bridge for data produced outside PHP: a [build hook](configuration.md#scripts-build-hooks) writes it, Freezed reads it. |
| an object | Any `ContentSourceInterface` instance, e.g. `new EntrySource($pdo)`. |

Script and JSON paths must lie inside the project directory.

### Items

A source delivers a list of items. Each item is an array (or JSON object)
with these keys:

| Key | Required | Description |
|-----|----------|-------------|
| `slug` | yes | Plays the role of the folder name: it becomes the output file name (`<slug>.<ext>`, unless the variables set `targetFileName`), the `folderName` key in `contentTypeCollection` and the target of `CONTENT:<type>/<slug>`. May contain `/` for nested output paths (`2026/09/launch`); must not contain `..`. Two items with the same slug fail the build. |
| `variables` | no | The item's variables, merged on top of the site-wide and the content type's variables exactly like a `variables.php`. |
| `template` | no | Template name inside `content/<type>/`, without extension. Defaults to `index`, so a restaurant item with `'template' => 'restaurant'` renders `content/entries/restaurant.html`. |

```php
// data/entries.php
<?php

$db = new PDO('sqlite:' . __DIR__ . '/site.sqlite');
$items = [];

foreach ($db->query('SELECT slug, title, kind, published, hero FROM entries') as $row) {
    $items[] = [
        'slug' => $row['slug'],
        'template' => $row['kind'],                 // restaurant.html, school.html, …
        'variables' => [
            'pageTitle' => $row['title'],
            'title' => $row['title'],
            'published' => $row['published'],
            'hero' => $row['hero'],                 // e.g. "2026/terrace.jpg", see below
        ],
    ];
}

return $items;
```

Do the denormalising in the source — neighbours, breadcrumbs, category
colours — so the templates stay simple and only read variables.

### A source class

```php
<?php

namespace App\Content;

use Neuedaten\Freezed\Domain\Source\ContentSourceInterface;

class EntrySource implements ContentSourceInterface
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = new PDO('sqlite:' . dirname(__DIR__, 2) . '/data/site.sqlite');
    }

    public function findAll(string $typeSlug, array $contentTypeConfig): iterable
    {
        $rows = $this->db->query("SELECT * FROM $typeSlug")->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            yield ['slug' => $row['slug'], 'variables' => $row];
        }
    }

    public function getVersion(string $typeSlug, array $contentTypeConfig): ?string
    {
        return (string) $this->db->query("SELECT MAX(updated_at) FROM $typeSlug")->fetchColumn();
    }
}
```

`findAll()` receives the type slug and the type's configuration, so one class
can serve several types. `getVersion()` returns any value that changes when
the data changes — the newest `updated_at`, a row count, a hash. `freezed
watch` polls it alongside the files and rebuilds when it differs, so a change
in the database shows up in the browser like a saved template does. Return
`null` when the source cannot tell; watch then only reacts to file changes. A
JSON source derives its version from the file; a script that returns a plain
array has none (returning a source object from the script provides one).

Register the class in your project's `composer.json`:

```json
"autoload": {
    "psr-4": { "App\\": "src/" }
}
```

### Images and downloads of sourced items

Items from a source have no folder of their own, so their images cannot sit
next to a `variables.php`. Put them in a folder inside the project, name it in
[`assetRoots`](configuration.md#assetroots) and keep the paths in the
variables relative to it:

```php
'assetRoots' => [
    'media' => 'data/media',        // or a symlink: data/media -> ../shared-media
],
```

```html
<img src="{freezed:image(src: hero, context: 'media', width: 800, fileType: 'webp')}" alt="{heroAlt}">
```

No machine path appears in the data or the templates; the variable holds
`2026/terrace.jpg`, the template names the root. See
[Where files may come from](#where-files-may-come-from).

### Listing sourced items elsewhere

`contentTypeCollection` reads the same index as everything else, so the
newest entries appear on a folder-based home page without further ado:

```html
<freezed:contentTypeCollection contentType="entries" orderBy="published" orderDirection="DESC" limit="3" as="latest">
    <f:for each="{latest}" as="entry">
        <freezed:link href="{entry.url}">{entry.title}</freezed:link>
    </f:for>
</freezed:contentTypeCollection>
```

Non-HTML outputs — a search index, GeoJSON, a feed — are just items of a type
with another `targetFileExtension`, or single items with a `targetFileName`
in their variables.

### Errors

An unusable `source` value, a script that returns no array, an item without a
slug, a duplicate slug or a `template` that does not exist stop the build with
a message naming the content type and the item. Template errors of sourced
items are reported as `content/entries/restaurant (item entries/seeblick)`.

## Listing items of a content type

Use the `contentTypeCollection` ViewHelper to pull every item of a content type
into a template — ideal for teaser lists, overview pages or menus that link to
items of another type (e.g. listing all `cases` from the home page).

```html
{namespace freezed=Neuedaten\Freezed\ViewHelpers}

<freezed:contentTypeCollection contentType="cases" orderBy="title" orderDirection="DESC" as="items">
    <f:for each="{items}" as="item">
        <a href="{item.url}">{item.title}</a>
        <p>{item.teaser}</p>
    </f:for>
</freezed:contentTypeCollection>
```

Each `item` contains every key from that item's variables (its `variables.php`,
or the `variables` of an item from a [content source](#content-sources)), plus
two derived keys:

- `folderName` — the item's directory name (e.g. `000-theasoft-typo3`), or
  its slug for items from a content source.
- `url` — the public path the item is built to (e.g. `/cases/theasoft-typo3.html`),
  derived from the content type's `targetDirectory` and the item's output
  filename. An `index.html` item yields its directory URL (`/cases/`), see
  [Public URLs](#public-urls).

The `as` variable only exists inside the tag.

### Arguments

| Argument | Required | Default | Description |
|----------|----------|---------|-------------|
| `contentType` | yes | — | The content type slug, matching a key in `freezed.config.php` and a folder under `content/`. |
| `as` | yes | — | Name of the variable the collected items are assigned to. |
| `orderBy` | no | `folderName` | Item key to sort by. `folderName` sorts by directory name; any other value (e.g. `title`) sorts by that key from `variables.php`. Two numeric values compare as numbers, two strings in natural order. |
| `orderDirection` | no | `ASC` | `ASC` or `DESC`. |
| `limit` | no | `100` | Maximum number of items, applied after sorting. `0` returns all items. |
| `filter` | no | — | Boolean expression every item must satisfy, in [`f:if` condition syntax](#filtering-items). `%key%` placeholders stand for the item's values. |

> Sorting by `folderName` is handy when you prefix item folders to control
> order — e.g. `000-…`, `001-…` — while keeping a clean `title` for display.

### Filtering items

`filter` takes a boolean expression and keeps only the items for which it
holds. The syntax is the one you already know from `<f:if condition="…">`:
`==`, `!=`, `<`, `>`, `<=`, `>=`, `!`, `&&`/`and`, `||`/`or`, parentheses,
quoted strings, numbers and `true`/`false`. A single `=` is accepted as `==`.

Item values are referenced as `%key%` — every key from `variables.php` plus the
derived `folderName` and `url`. Placeholders stay unquoted, exactly like a
variable in `f:if`; dot paths reach into nested arrays (`%meta.lang%`).

```html
<freezed:contentTypeCollection contentType="news" filter="%category% == 'News' && !%hidden%" as="items">
    …
</freezed:contentTypeCollection>
```

Because `filter` is a normal Fluid argument, variables of the surrounding
template are interpolated before the expression is evaluated. That makes
"related items" lists a one-liner — compare against the current page's own
values and leave the page itself out:

```html
<freezed:contentTypeCollection contentType="news" filter="%category% == '{category}' && %url% != '{url}'" limit="3" as="related">
    …
</freezed:contentTypeCollection>
```

Wrap interpolated values in quotes so a value with spaces stays one string.
Comparisons behave like PHP's loose comparison, so `%prio% > 5` works on
numbers and `%date% >= '2026-01-01'` on ISO dates. A `%key%` that an item does
not define evaluates like an undefined Fluid variable: `!%key%` and
`%key% == ''` hold, `%key%` alone does not. An expression that cannot be parsed
(e.g. an unclosed quote) fails the build with the offending filter in the
message. The filter runs before `orderBy` and `limit`, so `limit` counts the
matching items.

Combine `orderBy`, `orderDirection` and `limit` for "latest N" teasers:

```html
<freezed:contentTypeCollection contentType="blog" orderBy="date" orderDirection="DESC" limit="3" as="posts">
    <f:for each="{posts}" as="post">
        <freezed:link href="{post.url}">{post.title}</freezed:link>
    </f:for>
</freezed:contentTypeCollection>
```

## Sitemap

With `'sitemap' => ['enabled' => true]` in `freezed.config.php`, every build
writes a `public/sitemap.xml` that lists all pages of all content types. Two
optional keys in a page's `variables.php` control its entry:

```php
return [
    'pageTitle' => 'About',
    'lastmod' => '2026-01-31',   // <lastmod> for this page
    // 'sitemap' => false,        // leave this page out of the sitemap
];
```

`lastmod` accepts any `strtotime()`-parseable string or a `DateTimeInterface`.
Pages without one fall back to `sitemap.lastmod` from the config; if that is
unset too, the `<lastmod>` element is omitted. See
[Configuration › sitemap](configuration.md#sitemap) for the config keys and
`siteUrl`, which the sitemap needs for absolute URLs.
