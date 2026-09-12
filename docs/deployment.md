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

## Caching

Asset URLs from `freezed:resource` carry a content hash
(`main.css?v=a1b2c3d4`), so you can cache assets far longer than the HTML that
references them. Two rules make that safe:

**Don't cache HTML.** The HTML is what carries the current asset URLs, so it has
to be revalidated on every request:

```
Cache-Control: no-cache
```

`no-cache` means "revalidate before reuse", not "don't store" — a `304` still
saves the transfer. Don't use `no-store`.

**Be moderate with `?v=` assets.** A one-year `immutable` is tempting but not
safe here: some CDNs and proxies form their cache key from the path only and
drop the query string (Cloudflare's *Ignore Query String* caching level, for
example). A client that already holds the old body would keep it, and since the
path on disk is unchanged there is no second URL to fall back to. A week is a
good default:

```
Cache-Control: public, max-age=604800
```

If you have verified that your CDN keys on the full URI, you can raise it.

**Processed images are the exception.** `freezed:image` puts the hash into the
filename itself, so the URL *is* the cache key and no proxy can collapse it.
`public/images/` (`imagePublicDirectory`) can take the strongest header:

```
Cache-Control: public, max-age=31536000, immutable
```

### nginx

```nginx
location ~* \.html$ {
    add_header Cache-Control "no-cache";
}

# Processed images always carry a content hash in the filename.
location ^~ /images/ {
    add_header Cache-Control "public, max-age=31536000, immutable";
}

location ~* \.(css|js|svg|woff2?|png|jpe?g|webp|gif)$ {
    add_header Cache-Control "public, max-age=604800";
}
```

### Apache

```apache
<IfModule mod_headers.c>
  <FilesMatch "\.html$">
    Header set Cache-Control "no-cache"
  </FilesMatch>

  <FilesMatch "\.(css|js|svg|woff2?|png|jpe?g|webp|gif)$">
    Header set Cache-Control "public, max-age=604800"
  </FilesMatch>
</IfModule>

<IfModule mod_headers.c>
  <Directory "/var/www/my-site/images">
    Header set Cache-Control "public, max-age=31536000, immutable"
  </Directory>
</IfModule>
```

Files in `static/` are not versioned by default — they are meant to have stable
URLs. Keep their `Cache-Control` short, or enable
[`assetVersioningStatic`](configuration.md#assetversioningstatic) and reference
them through `{freezed:resource(path: '…', context: 'static')}`.

## Tips

- Set a real `siteName`, `siteLanguage` and `navigation` in `freezed.config.php`
  before deploying.
- Use root-absolute URLs (`/about.html`, `/assets/...`) so links work regardless
  of the page they're on.
- The `public/` folder is regenerated on every build and is git-ignored by
  default — build in CI rather than committing it.
- Add a `static/robots.txt` and `static/favicon.svg` (theme or project `static/`)
  for production-readiness.
- Set `siteUrl` and enable the [sitemap](configuration.md#sitemap), then point
  crawlers to it from `robots.txt`: `Sitemap: https://example.com/sitemap.xml`.
