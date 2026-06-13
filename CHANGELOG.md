# Changelog

All notable changes to **Freezed** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.2-beta] - 2026-06-13

### Fixed
- **Content types with a non-empty `targetDirectory` failed to write.** The build
  now creates the target subdirectory (e.g. `public/cases/`) before writing a
  page, instead of failing with a `file_put_contents(): No such file or
  directory` warning.
- **`watch`/`run` rebuilds could fail with "could not locate Composer
  autoloader".** When Freezed runs as a Composer dependency, subprocesses spawned
  for rebuilds were started via the real package bin (reached through a symlink),
  bypassing the Composer bin proxy that sets up the autoloader. The CLI now
  re-invokes itself through the proxy (`vendor/bin/freezed`) when available,
  falling back to the package bin for standalone clones.

### Added
- **Site-wide `variables`.** `freezed.config.php` now supports a top-level
  `variables` array, available to every content type and page. Variables are
  merged low-to-high: site-wide → `contentTypes.<type>.variables` → the page's
  `variables.php`. This lets shared values like `siteName`, `currentYear` or
  `navigation` be defined once instead of per content type. Backwards compatible:
  per-content-type `variables` keep working and override the site-wide ones.
- **`contentTypeCollection` ViewHelper.** Collects all items of a content type
  and exposes them to the child template under an `as` variable, for teaser
  lists, overview pages and menus, e.g.
  `<freezed:contentTypeCollection contentType="cases" orderBy="title" orderDirection="DESC" as="items">`.
  Each item holds every key from its `variables.php` plus the derived
  `folderName` and `url` keys. Sortable via `orderBy` (default `folderName`) and
  `orderDirection` (default `ASC`).

## [0.3.1-beta] - 2026-06-13

### Added
- **Fluid components.** Templates under a theme's `templates/components/` folder
  can now be called as typed, slot-aware tags via the `component` namespace,
  e.g. `<component:callout title="…">…</component:callout>` — no `f:render`
  required. Components use `<f:argument>` for typed inputs and `<f:slot>` for
  child content. Powered by Fluid 5.3's component feature and registered through
  the new `Neuedaten\Freezed\Components\ComponentCollection`. Adds the config key
  `themeComponentsPath` (default `/templates/components/`) and
  `Theme::getComponentRootPath()`.

## [0.3.0-beta] - 2026-06-13

### Changed
- **Upgraded the template engine to `typo3fluid/fluid: ^5.3`** (from `^2.10`).
  The rendering pipeline is unchanged; all Fluid APIs used by Freezed remain
  compatible.
- **Raised the minimum PHP version to `^8.2`** (required by Fluid 5 and already
  implied by `symfony/property-access: ^7.0`). Added an explicit `ext-mbstring`
  requirement.
- `ResourceViewHelper` now reads template paths via `$this->renderingContext`
  instead of the non-public `ViewHelperVariableContainer::getView()` chain.

## [0.2.0-beta] - 2026-06-13

### Added
- `freezed serve` — preview the build with PHP's built-in web server
  (`--host`, `--port`, defaults `localhost:8080`).
- `freezed watch` — rebuild automatically on source changes (polls
  `content/`, `themes/`, `static/` and `freezed.config.php`); each rebuild runs
  in a fresh subprocess to avoid stale singleton state.
- `freezed run` — development mode combining an initial build, a background
  server and the file watcher; stops the server cleanly on Ctrl+C.
- Build options (e.g. `--enviroment:development`) now work with every command
  and are forwarded to rebuilds in `watch`/`run`.
- Logging options `--verbose`, `--quiet`, and `--log` / `--log=path` (writes the
  full log to `freezed.log` in the project root by default).

### Changed
- `build` now prints a concise summary with page/file/resource counts and the
  render time instead of a bare `1`.
- Template render failures are caught and reported concisely (which template,
  which error) and abort the build with a non-zero exit code, instead of
  dumping a full stack trace.
- All output now flows through `LogService`, which filters what reaches the CLI
  by verbosity and can mirror everything to a log file.

## [0.1.1-beta] - 2026-06-12

## [0.1.0-beta] - 2026-06-10

First public beta.

### Added
- `freezed` CLI with `build` (alias `compile`), `install` (alias `init`),
  `help` and `version` commands.
- Static site rendering pipeline built on the TYPO3 Fluid template engine
  (layouts, partials, sections, ViewHelpers).
- Content model: content types as top-level folders under `content/`, pages as
  folders with an `index.html` template and a `variables.php`.
- Stackable themes under `themes/` with template/layout/partial resolution and
  static-file copying.
- `resource` ViewHelper for referencing and copying theme assets (CSS/JS/images)
  into the build.
- Build hooks via the `scripts` config (`start`, `end`, `beforeInstall`,
  `afterInstall`).
- Default theme `00_default`: responsive, typography-focused, light/dark with a
  toggle, plus example content (home, features, about).
- Robust project-root detection: `FREEZED_ROOT` env var, upward search for
  `freezed.config.php`, fallback to the current working directory.
- Documentation set under `docs/` (getting started, installation, concepts,
  content, themes, configuration, CLI, Docker, deployment).

### Notes
- This is a beta. The build pipeline is stable, but the public API may change
  before the 1.0 release.

[Unreleased]: https://github.com/neuedaten/freezed/compare/v0.3.2-beta...HEAD
[0.3.2-beta]: https://github.com/neuedaten/freezed/compare/v0.3.1-beta...v0.3.2-beta
[0.3.1-beta]: https://github.com/neuedaten/freezed/compare/v0.3.0-beta...v0.3.1-beta
[0.3.0-beta]: https://github.com/neuedaten/freezed/compare/v0.2.0-beta...v0.3.0-beta
[0.2.0-beta]: https://github.com/neuedaten/freezed/compare/v0.1.1-beta...v0.2.0-beta
[0.1.1-beta]: https://github.com/neuedaten/freezed/compare/v0.1.1-beta...v0.1.1-beta
[0.1.0-beta]: https://github.com/neuedaten/freezed/releases/tag/v0.1.0-beta
