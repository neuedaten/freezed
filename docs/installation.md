# Installation

Freezed can be used in two ways: as a **project skeleton** (best for new sites)
or as a **library** added to an existing project.

## Requirements

- PHP **8.2** or newer
- [Composer](https://getcomposer.org/)
- PHP extensions: `mbstring`, `dom`

## Option A — project skeleton (recommended)

```bash
composer create-project --stability=beta neuedaten/freezed-skeleton my-site
cd my-site
```

The skeleton declares `neuedaten/freezed` as a dependency and runs
`freezed install` automatically (via Composer's `post-create-project-cmd`),
scaffolding a ready-to-build site.

Build it:

```bash
./vendor/bin/freezed build
```

## Option B — library in an existing project

Add the engine to any Composer project:

```bash
composer require neuedaten/freezed
```

Then scaffold the project structure and build:

```bash
./vendor/bin/freezed install   # creates content/, themes/, static/, public/, freezed.config.php
./vendor/bin/freezed build
```

`freezed install` is **non-destructive**: it only creates folders that don't
exist and only copies the default theme, example content and config when the
target locations are empty. Running it again on an existing project is safe.

## Beta stability

Freezed is currently published as a **beta**, so both the engine and the skeleton
are only available as pre-release versions. Composer defaults to *stable*, so
during the beta you need to opt in:

- **The skeleton** — pass `--stability=beta` to `create-project` (the package's
  own `minimum-stability` does **not** affect how `create-project` selects the
  skeleton itself):

  ```bash
  composer create-project --stability=beta neuedaten/freezed-skeleton my-site
  ```

- **The library** — add an explicit beta flag when requiring it:

  ```bash
  composer require neuedaten/freezed:@beta
  ```

- **Or configure your project once** in `composer.json`:

  ```json
  {
      "minimum-stability": "beta",
      "prefer-stable": true
  }
  ```

Once a stable `1.0` is tagged, none of this is necessary — a plain
`composer require neuedaten/freezed` will resolve the stable release.

## How Freezed finds your project

When you run the `freezed` command, it determines the **project root** like this:

1. If the `FREEZED_ROOT` environment variable is set, that directory is used.
2. Otherwise Freezed searches upward from the current working directory for a
   `freezed.config.php` file.
3. If none is found (for example, before the first `install`), the current
   working directory is used.

This means you can run `freezed` from anywhere inside your project, and you can
point it at a different project with:

```bash
FREEZED_ROOT=/path/to/site ./vendor/bin/freezed build
```

## Installing from source (contributing)

```bash
git clone https://github.com/neuedaten/freezed
cd freezed
composer install
```

The repository is a monorepo: the engine lives in `packages/freezed/`, and the
repository root is a runnable skeleton/demo wired to the local package via a
Composer [path repository](https://getcomposer.org/doc/05-repositories.md#path).
