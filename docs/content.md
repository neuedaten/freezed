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

Site-wide defaults are defined once on the content type in `freezed.config.php`:

```php
'pages' => [
    'targetDirectory' => '',
    'targetFileExtension' => 'html',
    'variables' => [
        'siteName' => 'My Site',
        'navigation' => [
            ['label' => 'Home', 'url' => '/'],
            ['label' => 'About', 'url' => '/about.html'],
        ],
    ],
],
```

A page's `variables.php` is merged **on top** of these defaults, so any page can
override a default while still inheriting the rest.

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
