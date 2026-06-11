# Concepts

Freezed has a small set of concepts. Once they click, the whole tool fits in
your head.

## The build pipeline

Running `freezed build` performs these steps:

1. **Discover content types.** Each top-level folder in `content/` is a *content
   type* (e.g. `pages`). It must have a matching entry under `contentTypes` in
   `freezed.config.php`.
2. **Discover pages.** Inside each content type, every sub-folder is a *page*
   (e.g. `content/pages/home`).
3. **Clear the output.** The `public/` directory is emptied.
4. **Copy static files.** Everything in the project's `static/` folder and each
   theme's `static/` folder is copied into `public/` verbatim.
5. **Render pages.** Each page is rendered with Fluid, using the themes' template,
   layout and partial paths plus the page's own folder. Output is written to
   `public/`.
6. **Copy referenced resources.** Assets referenced via the `resource` ViewHelper
   are copied into `public/`.

## Content types

A content type defines a *kind* of page and how it is written out. The default
project ships with a single `pages` type configured like this:

```php
'contentTypes' => [
    'pages' => [
        'targetDirectory' => '',          // output sub-folder under public/
        'targetFileExtension' => 'html',  // default output extension
        'variables' => [ /* site-wide defaults */ ],
    ],
],
```

You can add more content types (for example `posts`) by creating
`content/posts/` and adding a `posts` entry to the config. See
[Content & pages](content.md).

## Pages

A page is a folder containing:

- **`index.html`** — a Fluid template (uses a layout and defines sections).
- **`variables.php`** — a PHP file returning an associative array of variables
  available to the template.

The folder name becomes the default output filename
(`content/pages/about` → `about.html`), unless you override it with a
`targetFileName` variable.

## Themes

A theme provides the shared structure and styling. Themes live in `themes/` and
each contains:

```text
themes/00_default/
├─ templates/
│  ├─ layouts/      # page layouts (e.g. page.html)
│  ├─ partials/     # reusable fragments (e.g. hero.html)
│  └─ templates/    # fallback templates
├─ assets/          # css, js, images — referenced via the resource ViewHelper
└─ static/          # copied verbatim into public/
```

Multiple themes **stack**: their template, layout and partial paths are all
registered, and themes later in alphabetical order override earlier ones. This
lets you ship a base theme and layer customisations on top. See [Themes](themes.md).

## Variables

Variables flow into templates from two places, merged together:

1. **Content-type defaults** — the `variables` array of the content type in
   `freezed.config.php` (good for site-wide values like `siteName` or navigation).
2. **Page variables** — the page's own `variables.php` (overrides the defaults).

## Output

The result is a plain `public/` directory of static HTML and assets. There is no
runtime: deploy it to any static host, CDN or web server. See
[Deployment](deployment.md).
