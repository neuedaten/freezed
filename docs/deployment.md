# Deployment

A Freezed build is just a folder of static files (`public/`). There is no runtime,
so you can host it almost anywhere.

## Build for production

```bash
./vendor/bin/freezed build
```

Everything you need is now in `public/`. Upload its **contents** to your host.

## Static hosts

Any static host works. A few common options:

### Netlify

- **Build command:** `composer install && ./vendor/bin/freezed build`
- **Publish directory:** `public`

### GitHub Pages (via Actions)

```yaml
# .github/workflows/deploy.yml
name: Deploy
on:
  push:
    branches: [main]
jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, dom
      - run: composer install --no-dev --prefer-dist
      - run: ./vendor/bin/freezed build
      - uses: actions/upload-pages-artifact@v3
        with:
          path: public
  deploy:
    needs: build
    runs-on: ubuntu-latest
    permissions:
      pages: write
      id-token: write
    environment:
      name: github-pages
    steps:
      - uses: actions/deploy-pages@v4
```

### Plain web server (nginx / Apache)

Copy `public/` to your document root:

```bash
rsync -av --delete public/ user@server:/var/www/my-site/
```

## Tips

- Set a real `siteName`, `siteLanguage` and `navigation` in `freezed.config.php`
  before deploying.
- Use root-absolute URLs (`/about.html`, `/assets/...`) so links work regardless
  of the page they're on.
- The `public/` folder is regenerated on every build and is git-ignored by
  default — build in CI rather than committing it.
- Add a `static/robots.txt` and `static/favicon.svg` (theme or project `static/`)
  for production-readiness.
