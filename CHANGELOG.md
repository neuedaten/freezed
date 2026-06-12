# Changelog

All notable changes to **Freezed** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/neuedaten/freezed/compare/v0.1.0-beta...HEAD
[0.1.0-beta]: https://github.com/neuedaten/freezed/releases/tag/v0.1.0-beta
