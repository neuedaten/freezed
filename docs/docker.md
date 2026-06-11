# Docker

The [freezed-skeleton](https://github.com/neuedaten/freezed-skeleton) starter
ships a containerised environment so you can build and preview a Freezed site
without installing PHP or Composer locally. The files below come with the
skeleton (or any project you copy them into).

## What's included

- **`Dockerfile`** — PHP 8.3 CLI with the `mbstring` extension and Composer.
- **`docker-compose.yml`** — a single `freezed` service that builds and serves
  the site on port `8080`.
- **`docker/entrypoint.sh`** — installs dependencies, scaffolds (if needed),
  builds, and serves `public/`.

## Quick start

```bash
docker compose up --build
```

Then open <http://localhost:8080>.

On startup the container:

1. runs `composer install` (first run only);
2. runs `freezed install` if no `freezed.config.php` exists yet;
3. runs `freezed build`;
4. serves `public/` with PHP's built-in web server on port 8080.

## Rebuilding after changes

The project directory is mounted into the container, so edits on your host are
visible immediately. Re-run the build without restarting:

```bash
docker compose exec freezed ./vendor/bin/freezed build
```

Then refresh your browser.

## Stopping and cleaning up

```bash
docker compose down            # stop the container
docker compose down -v         # also remove the cached vendor/ volume
```

## Configuration notes

- The site is served on port **8080**. Change the mapping in `docker-compose.yml`
  (`"8080:8080"`) if that port is taken.
- Composer dependencies are cached in a named volume (`freezed_vendor`) for
  faster restarts. Remove it with `docker compose down -v` to force a clean
  reinstall.
- Set a different project root by adding `FREEZED_ROOT` to the service's
  environment in `docker-compose.yml`.
