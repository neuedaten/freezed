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
        ['label' => 'Home', 'url' => '/'],
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

Each `item` contains every key from that item's `variables.php`, plus two
derived keys:

- `folderName` — the item's directory name (e.g. `000-theasoft-typo3`).
- `url` — the public path the item is built to (e.g. `/cases/theasoft-typo3.html`),
  derived from the content type's `targetDirectory` and the item's output
  filename.

The `as` variable only exists inside the tag.

### Arguments

| Argument | Required | Default | Description |
|----------|----------|---------|-------------|
| `contentType` | yes | — | The content type slug, matching a key in `freezed.config.php` and a folder under `content/`. |
| `as` | yes | — | Name of the variable the collected items are assigned to. |
| `orderBy` | no | `folderName` | Item key to sort by. `folderName` sorts by directory name; any other value (e.g. `title`) sorts by that key from `variables.php`. |
| `orderDirection` | no | `ASC` | `ASC` or `DESC`. |

> Sorting by `folderName` is handy when you prefix item folders to control
> order — e.g. `000-…`, `001-…` — while keeping a clean `title` for display.
