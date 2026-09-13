# Changelog

All notable changes to **Freezed** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.7.0-beta] - 2026-09-13

### Changed
- **Processed images are output in sub-folders that mirror the source.**
  `freezed:image` now writes to `images/<path below content/ or themes/>/`,
  e.g. `images/pages/home/assets/hero_800x600_q90_a1b2c3d4.webp` for
  `content/pages/home/assets/hero.jpg` and
  `images/00_default/assets/images/hero_…webp` for a theme image — the same
  sub-folders `freezed:resource` already uses. The image cache in
  `var/cache/images/` follows the same structure and `cache:flush` clears it
  recursively. Dashes and underscores in folder and file names are kept in the
  slug instead of being dropped, so `news-1` and `news1` stay distinct.
  Image URLs change with this release; run `./vendor/bin/freezed cache:flush`
  once after upgrading to drop the old flat cache files.

### Fixed
- **`freezed:image` could mix up same-named images from different content
  folders.** Generated files were named after the immediate parent folder and
  the file name only, so `content/pages/home/assets/hero.jpg` and
  `content/news/launch/assets/hero.jpg` both became
  `images/assets-hero_800x600.webp` and overwrote each other in `public/` and
  in the image cache — the first one built won for both pages. Since
  0.6.0-beta the content hash in the name told them apart, but only by
  coincidence of differing content. The sub-folder output above fixes this
  structurally. `freezed:resource` was never affected: copied files have
  always landed under `public/<contentType>/<folder>/…`.

## [0.6.0-beta] - 2026-09-12

### Added
- **Asset versioning (cache busting).** URLs returned by `freezed:resource` now
  carry a short hash of the file's content, e.g.
  `/00_default/assets/css/main.css?v=a1b2c3d4`, so browsers pick up a changed
  asset after a deployment instead of serving the old one from cache. The file
  keeps its name on disk, so relative `url()` references inside CSS keep
  working. The version is deliberately a content hash and not a modification
  time: `git clone` resets mtimes, so building in CI would otherwise invalidate
  every asset on every deployment. Configurable via `assetVersioning`
  (default `true`).
- **`context: 'static'` for `freezed:resource`.** Resolves a file from the
  project's and the themes' `static/` folders and returns its public URL, e.g.
  `{freezed:resource(path: 'favicon.svg', context: 'static')}`, following the
  same override order the files are copied in. Static URLs stay unversioned
  unless `assetVersioningStatic` is enabled, because `static/` exists to deliver
  stable paths. The `00_default` theme now links its favicons this way instead
  of hardcoding them.

### Fixed
- **`freezed:image` served stale images from its cache.** The generated filename
  encoded only folder, name and target resolution, so replacing a source image
  or changing `quality` silently reused the old file — despite the
  documentation claiming otherwise. Source content and quality are now part of
  the name (`images-hero_800x600_q80_a1b2c3d4.webp`), which fixes the cache key
  and makes image URLs cache-busting at the same time. Passed-through sources
  (e.g. SVG) are covered too. Superseded files stay in `var/cache/images/`
  without being published; run `./vendor/bin/freezed cache:flush` once after
  upgrading to clear them out.
- **`freezed:resource` failed late on a missing file.** A path that could not be
  resolved was registered as a resource with an empty source path and only
  failed when the build tried to copy it. It now logs a warning and returns an
  empty string.
- **Deprecation warnings while rendering `freezed:image` on PHP 8.5.** The GD
  path freed the source and target images with `imagedestroy()`, which PHP 8.5
  deprecates because it has had no effect since PHP 8.0 — GD images are
  ordinary objects since then and the garbage collector releases them. The two
  calls are gone, so builds stay free of `PHP Deprecated` output.

### Removed
- Unused `uniqid()`-based filename generation in `ResourceRepository` and the
  dead `convertedName`, `mimeType`, `originalName` and `config` fields on the
  `Resource` model. The generated name was never read; being time-based, wiring
  it up would have broken build reproducibility.

## [0.5.0-beta] - 2026-09-09

### Added
- **`limit` argument for `contentTypeCollection`.** Caps the number of items
  exposed to the template, applied after sorting, e.g.
  `<freezed:contentTypeCollection contentType="blog" orderBy="date" orderDirection="DESC" limit="3" as="posts">`.
  Defaults to `100`; `limit="0"` returns all items.

## [0.4.1-beta] - 2026-09-09

### Changed
- **Default theme icon.** The scaffolded `00_default` theme now ships the
  Freezed icon (pixel snowflake, lime on violet) as `static/favicon.svg`, plus a
  `favicon-32.png` fallback and a 512 px `apple-touch-icon.png`, all linked from
  the page layout. The header mark is the same snowflake as an inline SVG with
  `fill="currentColor"`, so it follows the light/dark theme; `assets/images/logo.svg`
  contains the same mark for use in your own templates.
- **Default theme footer.** The footer of the `00_default` theme gained a
  centred credit line linking to [neuedaten.de](https://neuedaten.de).
  Existing projects are not changed: `freezed install` copies the theme once,
  so copy the new files from `assets/themes/00_default/` by hand if you want
  them in a site that was scaffolded with an earlier release.

## [0.4.0-beta] - 2026-09-06

### Added
- **`link` ViewHelper.** Renders an `<a>` tag in the spirit of TYPO3's `f:link`.
  `href` accepts an absolute URL, a relative URL or a content reference
  `CONTENT:<contentType>/<pageFolder>` (e.g.
  `<freezed:link href="CONTENT:pages/impressum">Imprint</freezed:link>`) that is
  resolved to the page's public URL at build time from the content type's
  `targetDirectory` and the page's output filename. Optional `section` (anchor)
  and `absolute` (prefix with `siteUrl`) arguments; every other attribute
  (`class`, `target`, `rel`, `title`, `data-*`, …) is passed through, and
  `target="_blank"` without `rel` gets `rel="noopener"`. A reference that does
  not match any page renders a `<span class="dead-link">` with the same content
  and attributes instead of a link and logs a warning; the build still succeeds.
- **Sitemap generation.** With `'sitemap' => ['enabled' => true]` in
  `freezed.config.php`, every build writes `public/sitemap.xml` listing all
  pages of all content types. `<lastmod>` comes from a `lastmod` key in the
  page's `variables.php`, falling back to `sitemap.lastmod` from the config, and
  is omitted when neither is set. Pages opt out with `'sitemap' => false`. The
  CLI summary shows `sitemap` when the file was written.
- **`siteUrl` config key.** Public base URL of the site, used for absolute URLs
  in the sitemap and by `<freezed:link absolute="true">`.

### Changed
- **Page URLs for index files.** A page whose output file is `index.<ext>` now
  has the directory URL everywhere Freezed derives one: `/` instead of
  `/index.html`, `/cases/` instead of `/cases/index.html`. This affects the
  `url` key of `contentTypeCollection` items.
- **Content lookups are shared per build.** `contentTypeCollection` and `link`
  reuse one content index instead of re-reading `content/` on every render.
- **Scaffold navigation** uses `<freezed:link href="{item.href}">` with
  `CONTENT:` references (`href` key instead of `url` in the `navigation` array).

### Fixed
- **`contentPath` was ignored when discovering content types.** The build always
  read `content/` regardless of the configured `contentPath`; it now honours the
  setting like `install`, `watch` and the resource resolver already did.
- **Docs: variable precedence.** `docs/concepts.md` described two merge levels;
  it now documents all three (site-wide → content type → page).

## [0.3.3-beta] - 2026-06-13

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
- **`image` ViewHelper.** Resizes, converts and re-encodes images at build time,
  e.g. `<img src="{freezed:image(src: 'assets/images/hero.jpg', context: 'theme', width: 800, fileType: 'webp', quality: 80)}">`.
  Arguments: `src`, `context` (like `resource`), `width`/`height` (px or `auto`,
  aspect ratio preserved), `fileType` (jpg/png/webp/gif), `quality`, `scaleUp`
  (default `false`). Uses Imagick when available, otherwise GD; unsupported
  source types (e.g. SVG) are passed through. Processed files are named after the
  source folder, original name and target resolution (e.g.
  `images-hero_800x600.webp`), cached under `var/cache/images/` and copied into
  `public/` on each build. New config keys `imageCacheDirectory`,
  `imagePublicDirectory`, `imageDefaultQuality`.
- **`cache:flush` command.** `freezed cache:flush` removes all cached processed
  images so the next build regenerates them.
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

[Unreleased]: https://github.com/neuedaten/freezed/compare/v0.7.0-beta...HEAD
[0.7.0-beta]: https://github.com/neuedaten/freezed/compare/v0.6.0-beta...v0.7.0-beta
[0.6.0-beta]: https://github.com/neuedaten/freezed/compare/v0.5.0-beta...v0.6.0-beta
[0.5.0-beta]: https://github.com/neuedaten/freezed/compare/v0.4.1-beta...v0.5.0-beta
[0.4.1-beta]: https://github.com/neuedaten/freezed/compare/v0.4.0-beta...v0.4.1-beta
[0.4.0-beta]: https://github.com/neuedaten/freezed/compare/v0.3.3-beta...v0.4.0-beta
[0.3.3-beta]: https://github.com/neuedaten/freezed/compare/v0.3.2-beta...v0.3.3-beta
[0.3.2-beta]: https://github.com/neuedaten/freezed/compare/v0.3.1-beta...v0.3.2-beta
[0.3.1-beta]: https://github.com/neuedaten/freezed/compare/v0.3.0-beta...v0.3.1-beta
[0.3.0-beta]: https://github.com/neuedaten/freezed/compare/v0.2.0-beta...v0.3.0-beta
[0.2.0-beta]: https://github.com/neuedaten/freezed/compare/v0.1.1-beta...v0.2.0-beta
[0.1.1-beta]: https://github.com/neuedaten/freezed/compare/v0.1.1-beta...v0.1.1-beta
[0.1.0-beta]: https://github.com/neuedaten/freezed/releases/tag/v0.1.0-beta
