<div align="center">

# ❄️ Freezed

**A static site generator powered by the [TYPO3 Fluid](https://github.com/TYPO3/Fluid) template engine.**

Templates in, static files out. No database, no runtime, no surprises.

[![Beta](https://img.shields.io/badge/status-beta-blue)](#status)
[![License: GPL-2.0-or-later](https://img.shields.io/badge/license-GPL--2.0--or--later-green)](LICENSE)
[![PHP ≥ 8.1](https://img.shields.io/badge/php-%E2%89%A5%208.1-777bb4)](#requirements)

</div>

---

## What is Freezed?

Freezed renders a folder of content and one or more themes into a plain static
website using **Fluid** — the same templating engine that powers TYPO3 CMS. You
write layouts, partials, sections and (optionally) custom ViewHelpers, and
Freezed compiles everything to static HTML and assets in a `public/` directory
you can host anywhere.

```text
content/ + themes/   ──►   freezed build   ──►   public/  (static HTML + assets)
```

## Features

- **Fluid templating** — layouts, partials, sections and ViewHelpers.
- **Stackable themes** — drop themes into `themes/`; they layer and override cleanly.
- **Content as folders** — every page is a folder with a template and a `variables.php`.
- **Asset pipeline** — reference CSS/JS/images via the `resource` ViewHelper.
- **Static files** — anything in `static/` is copied verbatim into the build.
- **Build hooks** — run shell commands before and after a build.
- **Zero runtime** — the output is just files; host it on any static host or CDN.

## Quick start

### Start a new site (recommended)

Use the starter skeleton, which scaffolds a working site for you:

```bash
composer create-project neuedaten/freezed-skeleton my-site
cd my-site
./vendor/bin/freezed build
# open public/index.html
```

The skeleton also ships a Docker dev environment (`docker compose up --build`).
See [neuedaten/freezed-skeleton](https://github.com/neuedaten/freezed-skeleton).

### Add Freezed to an existing project

```bash
composer require neuedaten/freezed
./vendor/bin/freezed install   # scaffold content/, themes/, config
./vendor/bin/freezed build     # render into public/
```

## Project structure

A Freezed site is just a handful of folders:

```text
my-site/
├─ content/              # your pages, grouped by content type
│  └─ pages/
│     └─ home/
│        ├─ index.html   # a Fluid template
│        └─ variables.php # variables for this page
├─ themes/               # one or more stackable themes
│  └─ 00_default/
│     ├─ templates/{layouts,partials,templates}/
│     ├─ assets/{css,js,images}/
│     └─ static/
├─ static/               # files copied verbatim into the build
├─ public/               # generated output (git-ignored)
└─ freezed.config.php    # content types, default variables, build hooks
```

## Documentation

| Guide | What it covers |
|-------|----------------|
| [Getting started](docs/getting-started.md) | Install, first build, the dev loop |
| [Installation](docs/installation.md) | Skeleton, library, requirements |
| [Concepts](docs/concepts.md) | The build pipeline and core ideas |
| [Content & pages](docs/content.md) | Writing pages, variables, content types |
| [Themes](docs/themes.md) | Layouts, partials, assets, the resource ViewHelper |
| [Configuration](docs/configuration.md) | `freezed.config.php` reference |
| [CLI](docs/cli.md) | The `freezed` command |
| [Docker](docs/docker.md) | The containerised dev environment (ships with the skeleton) |
| [Deployment](docs/deployment.md) | Hosting the static output |

## Requirements

- PHP **8.1** or newer
- [Composer](https://getcomposer.org/)
- PHP extensions: `mbstring`, `dom` (both ship with most PHP builds)

## This repository

This is the **engine** — the `freezed` CLI and rendering pipeline, published as
the Composer package [`neuedaten/freezed`](https://packagist.org/packages/neuedaten/freezed).

```text
.
├─ bin/freezed            # CLI entry point
├─ Classes/               # PSR-4: Neuedaten\Freezed\
│  ├─ Commands/           # install / compile commands
│  ├─ Domain/             # models + repositories (content, themes, resources)
│  ├─ Services/           # config, compile, render, file, static, scripts, log
│  └─ ViewHelpers/        # ResourceViewHelper
├─ assets/                # default theme + example content + default config
├─ includes/config.php    # built-in path defaults
└─ docs/                  # documentation
```

A ready-to-build starter project lives in the companion repo
[neuedaten/freezed-skeleton](https://github.com/neuedaten/freezed-skeleton).

## Status

Freezed is in **beta**. The build pipeline is stable; the API may still change
before 1.0. Issues and feedback are very welcome.

## License

[GPL-2.0-or-later](LICENSE) © Bastian Schwabe / neuedaten
