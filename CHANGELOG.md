# Changelog

All notable changes to **Freezed** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/neuedaten/freezed/compare/v0.2.0-beta...HEAD
[0.2.0-beta]: https://github.com/neuedaten/freezed/compare/v0.1.1-beta...v0.2.0-beta
[0.1.1-beta]: https://github.com/neuedaten/freezed/compare/v0.1.1-beta...v0.1.1-beta
[0.1.0-beta]: https://github.com/neuedaten/freezed/releases/tag/v0.1.0-beta
