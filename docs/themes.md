# Themes

A theme provides the shared layout, partials, styling and assets for your site.

## Theme structure

```text
themes/00_default/
├─ templates/
│  ├─ layouts/      # page layouts, selected with <f:layout name="…" />
│  ├─ partials/     # reusable fragments, rendered with <f:render partial="…" />
│  ├─ components/   # typed, slot-aware tags, called as <component:name />
│  └─ templates/    # fallback templates (used when a page has no own template)
├─ assets/          # css / js / images — copied on demand via the resource ViewHelper
└─ static/          # copied verbatim into public/ on every build
```

## Layouts

A layout is the page shell. The default theme's `layouts/page.html` looks roughly
like this:

```html
<!doctype html>
<html lang="{siteLanguage}">
<head>
    <title>{pageTitle} · {siteName}</title>
    <link rel="stylesheet"
          href="{freezed:resource(path: 'assets/css/main.css', context: 'theme')}">
</head>
<body>
    <f:render partial="site-header" arguments="{_all}" />
    <main>
        <f:render section="content" />
    </main>
    <f:render partial="site-footer" arguments="{_all}" />
</body>
</html>
```

- `<f:render section="content" />` pulls in the `content` section a page defines.
- `arguments="{_all}"` forwards all current variables to a partial.

## Partials

Partials are reusable fragments in `templates/partials/`. Render one with:

```html
<f:render partial="hero" arguments="{ title: 'Hello', subtitle: 'World' }" />
```

Inside `partials/hero.html`, the passed arguments are available as variables
(`{title}`, `{subtitle}`).

## Components

Components are the modern alternative to partials: reusable, **typed** template
tags with **slots** for child content. They live in `templates/components/` and
are called like HTML tags via the globally registered `component` namespace — no
`f:render` and no `xmlns` declaration needed.

Each component lives in its own folder (so component-specific assets can sit next
to it). The tag name maps to the folder/file: `<component:callout>` resolves to
`templates/components/Callout/Callout.html`.

```html
<!-- templates/components/Callout/Callout.html -->
<f:argument name="title" type="string" />
<f:argument name="variant" type="string" optional="{true}" default="info" />

<aside class="callout callout--{variant}">
    <h3 class="callout__title">{title}</h3>
    <div class="callout__body">
        <f:slot />
    </div>
</aside>
```

Call it from any template:

```html
<component:callout title="Heads up" variant="warning">
    <p>This text is passed into the component's <code>&lt;f:slot /&gt;</code>.</p>
</component:callout>
```

Two things set components apart from partials:

- **Typed arguments.** `<f:argument>` declares each input (with `type`,
  `optional` and `default`). Passing an undeclared argument is an error, which
  catches typos early.
- **Isolation.** A component only sees its own arguments and slot content — not
  the surrounding template variables. That makes it reliably reusable. Pass
  everything it needs explicitly as arguments.

For multiple insertion points, give slots names and fill them with `<f:fragment>`:

```html
<!-- component definition -->
<div class="media"><f:slot name="media" /></div>
<div class="body"><f:slot name="content" /></div>

<!-- usage -->
<component:mediaText>
    <f:fragment name="media"><img src="…" alt="…"></f:fragment>
    <f:fragment name="content"><p>…</p></f:fragment>
</component:mediaText>
```

Components and ViewHelpers can be nested freely. Reach for a partial when you
just need to factor out shared markup that uses the page's variables; reach for a
component when you want a self-contained, parameterised building block.

## Assets and the `resource` ViewHelper

Theme assets (CSS, JS, images) live under `assets/` and are **not** copied
automatically. Instead, you reference them with the `resource` ViewHelper, which
copies the file into the build and returns its public URL:

```html
<link rel="stylesheet"
      href="{freezed:resource(path: 'assets/css/main.css', context: 'theme')}">

<script src="{freezed:resource(path: 'assets/js/main.js', context: 'theme')}" defer></script>

<img src="{freezed:resource(path: 'assets/images/logo.svg', context: 'theme')}" alt="Logo">
```

- `path` is relative to the theme root.
- `context: 'theme'` resolves the asset across the theme stack (so a later theme
  can override an earlier theme's file).
- `context: 'static'` resolves the file from the `static/` folders instead, see
  [Static files](#static-files).

The returned URL carries a short hash of the file's content
(`main.css?v=a1b2c3d4`) so browsers pick up a changed asset after a deployment.
The name on disk is unchanged. See
[`assetVersioning`](configuration.md#assetversioning) to turn it off, and
[Caching](deployment.md#caching) for the matching `Cache-Control` headers.

The `freezed` namespace is registered globally, so no `xmlns` declaration is
required — though you may add `{namespace freezed=Neuedaten\Freezed\ViewHelpers}`
for editor support.

Links between pages should use the [`link` ViewHelper](content.md#linking-between-pages),
which resolves `CONTENT:pages/about` references to the page's public URL and
survives renamed output files.

## Static files

Anything in a theme's `static/` folder is copied **verbatim** into `public/` on
every build, keeping its relative path. Use it for files that need a fixed path,
such as `favicon.svg`, `robots.txt` or `.well-known/` files.

The project's own `static/` folder is copied first, then each theme's in theme
order, and every copy overwrites the previous one. So for static files a
**theme wins over the project**, and a later theme wins over an earlier one —
the reverse of what the folder order might suggest.

You can reference a static file through the `resource` ViewHelper instead of
hardcoding its path:

```html
<link rel="icon" href="{freezed:resource(path: 'favicon.svg', context: 'static')}" type="image/svg+xml">
```

`path` is relative to the `static/` root. The file is still copied by the normal
static-file pass — the ViewHelper only resolves the URL, following the same
override order. Static URLs stay unversioned unless you enable
[`assetVersioningStatic`](configuration.md#assetversioningstatic); that is
worth doing for favicons, which browsers cache aggressively.

## Stacking themes

All folders in `themes/` are active at once. Their template, layout and partial
paths are merged, and Freezed resolves a template by searching the paths in
reverse order — so a theme that sorts **later** alphabetically overrides one that
sorts earlier.

A common pattern is a numeric prefix:

```text
themes/
├─ 00_default/   # base theme (shipped with Freezed)
└─ 10_brand/     # your overrides — wins over 00_default
```

To override a single partial, copy just that file into your higher-priority
theme; everything else still comes from the base theme.

## Custom ViewHelpers

Need logic Fluid doesn't provide out of the box? Write a ViewHelper in PHP. See
[ViewHelpers in the Fluid documentation](https://docs.typo3.org/other/typo3fluid/fluid/main/en-us/)
and the bundled ViewHelpers in `vendor/neuedaten/freezed/Classes/ViewHelpers/`
as references: `ResourceViewHelper`, `ImageViewHelper`,
`ContentTypeCollectionViewHelper` and the tag-based `LinkViewHelper`.
