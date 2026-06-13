# Getting started

This guide takes you from nothing to a built site in a few minutes.

## 1. Requirements

- PHP **8.2+**
- [Composer](https://getcomposer.org/)
- PHP extensions `mbstring` and `dom`

Check your PHP version:

```bash
php -v
```

No local PHP? Skip to [Docker](docker.md) — everything runs in a container.

## 2. Create a project

The fastest way to start is the skeleton, which scaffolds a working site for you:

```bash
composer create-project --stability=beta neuedaten/freezed-skeleton my-site
cd my-site
```

This installs the `neuedaten/freezed` engine and runs `freezed install`, which
creates `content/`, `themes/`, `static/`, `public/` and a `freezed.config.php`,
seeded with the default theme and a few example pages.

> Prefer adding Freezed to an existing project? See [Installation](installation.md).

## 3. Build the site

```bash
./vendor/bin/freezed build
```

Freezed renders everything in `content/` through the themes in `themes/` and
writes the result to `public/`. Open `public/index.html` in your browser.

## 4. The development loop

1. Edit a template in `content/…/index.html` or a theme file in `themes/…`.
2. Run `./vendor/bin/freezed build` again.
3. Refresh your browser.

To avoid opening files directly, serve `public/` with PHP's built-in server:

```bash
php -S localhost:8080 -t public
```

Or use the Docker environment, which builds and serves in one step — see
[Docker](docker.md).

## 5. Next steps

- [Concepts](concepts.md) — understand how a build works.
- [Content & pages](content.md) — add and structure your own pages.
- [Themes](themes.md) — customise the look or build your own theme.
- [Configuration](configuration.md) — the `freezed.config.php` reference.
