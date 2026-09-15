<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Domain\Model\ContentType;

/**
 * Generates public/sitemap.xml (sitemaps.org protocol) from all content items
 * when enabled in freezed.config.php:
 *
 *     'siteUrl' => 'https://example.com',
 *     'sitemap' => [
 *         'enabled' => true,
 *         'lastmod' => null,          // fallback for items without their own lastmod
 *         'lastmodFrom' => 'lastmod', // item variable that holds the date
 *         'excludeWhen' => null,      // item variable whose truthy value excludes, e.g. 'noindex'
 *     ],
 *
 * Only HTML documents are listed: items whose public path ends in a
 * directory, has no extension or ends in .html/.htm. Other output files
 * (llms.txt, robots.txt, …) are skipped unless the item opts in.
 *
 * Per item (variables.php):
 *   'lastmod' => '2026-01-31'   // any strtotime()-parseable value or DateTimeInterface
 *   'sitemap' => false          // always exclude this item
 *   'sitemap' => true           // always include it, regardless of excludeWhen and file type
 */
class SitemapService
{
    public const FILE_NAME = 'sitemap.xml';

    /** @var array{enabled: bool, lastmod: mixed, lastmodFrom: string, excludeWhen: string|null} */
    public const DEFAULTS = [
        'enabled' => false,
        'lastmod' => null,
        'lastmodFrom' => 'lastmod',
        'excludeWhen' => null,
    ];

    protected static self|null $instance = null;

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Write the sitemap into the public directory when enabled.
     *
     * @return bool True when a sitemap was written.
     */
    public function write(FileService $fileService): bool
    {
        $config = $this->getConfig();
        if (!$config['enabled']) {
            return false;
        }

        if ($fileService->fileExists(self::FILE_NAME)) {
            LogService::getInstance()->warning(
                'Sitemap: public/' . self::FILE_NAME . ' was already written by a content item or a static file '
                . 'and is now overwritten by the generated sitemap. Rename that file or disable "sitemap".'
            );
        }

        $fileService->writeFile(self::FILE_NAME, $this->generate($config));

        return true;
    }

    /**
     * Build the sitemap XML for all content items.
     *
     * @param array{enabled?: bool, lastmod?: mixed, lastmodFrom?: string, excludeWhen?: string|null} $config
     *        Sitemap options; missing keys fall back to their defaults.
     */
    public function generate(array $config = []): string
    {
        $config += self::DEFAULTS;
        $urlService = ContentUrlService::getInstance();

        $siteUrl = $urlService->getSiteUrl();
        if ($siteUrl === '') {
            LogService::getInstance()->warning(
                'Sitemap: "siteUrl" is not set in freezed.config.php, writing root-relative <loc> entries. '
                . 'Set siteUrl (e.g. https://example.com) so search engines can use the sitemap.'
            );
        }

        $defaultDate = $this->formatDate($config['lastmod'], 'sitemap.lastmod in freezed.config.php');

        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($urlService->getAllItems() as $model) {
            if (!$this->isIncluded($model, $config['excludeWhen'])) {
                continue;
            }

            $loc = $siteUrl !== '' ? $urlService->getUrl($model, true) : $model->getPublicPath();

            $itemLastmod = $model->getVariables()[$config['lastmodFrom']] ?? null;
            $lastmod = $this->formatDate($itemLastmod, $model->getTypeSlug() . '/' . $model->getTitle())
                ?? $defaultDate;

            $lines[] = '  <url>';
            $lines[] = '    <loc>' . self::escape($loc) . '</loc>';
            if ($lastmod !== null) {
                $lines[] = '    <lastmod>' . $lastmod . '</lastmod>';
            }
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines) . "\n";
    }

    /**
     * Normalised sitemap configuration. Accepts `'sitemap' => true` as a
     * shorthand for `['enabled' => true]`.
     *
     * @return array{enabled: bool, lastmod: mixed, lastmodFrom: string, excludeWhen: string|null}
     */
    private function getConfig(): array
    {
        $raw = ConfigService::getInstance()->getValue('[sitemap]');

        if ($raw === true) {
            return ['enabled' => true] + self::DEFAULTS;
        }

        if (!is_array($raw)) {
            return self::DEFAULTS;
        }

        $lastmodFrom = $raw['lastmodFrom'] ?? null;
        $excludeWhen = $raw['excludeWhen'] ?? null;

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'lastmod' => $raw['lastmod'] ?? null,
            'lastmodFrom' => is_string($lastmodFrom) && $lastmodFrom !== '' ? $lastmodFrom : self::DEFAULTS['lastmodFrom'],
            'excludeWhen' => is_string($excludeWhen) && $excludeWhen !== '' ? $excludeWhen : null,
        ];
    }

    /**
     * Decide whether an item is listed. The item's own 'sitemap' variable is
     * authoritative: false always excludes, true always includes. Otherwise
     * the item is excluded when the excludeWhen variable (e.g. 'noindex') is
     * truthy, or when its output is not an HTML document.
     */
    private function isIncluded(ContentType $model, ?string $excludeWhen): bool
    {
        $variables = $model->getVariables();

        if (array_key_exists('sitemap', $variables)) {
            if ($variables['sitemap'] === false) {
                return false;
            }
            if ($variables['sitemap'] === true) {
                return true;
            }
        }

        if ($excludeWhen !== null && !empty($variables[$excludeWhen])) {
            return false;
        }

        return self::isDocument($model->getPublicPath());
    }

    /**
     * True for public paths that denote an HTML document: a directory URL
     * ("/cases/"), an extensionless path ("/about") or a .html/.htm file.
     */
    public static function isDocument(string $publicPath): bool
    {
        if ($publicPath === '' || str_ends_with($publicPath, '/')) {
            return true;
        }

        $lastSegment = substr($publicPath, strrpos($publicPath, '/') + 1);
        $dot = strrpos($lastSegment, '.');
        if ($dot === false) {
            return true;
        }

        return in_array(strtolower(substr($lastSegment, $dot + 1)), ['html', 'htm'], true);
    }

    /**
     * Format a lastmod value as a W3C date (Y-m-d). Accepts DateTimeInterface,
     * a Unix timestamp or any strtotime()-parseable string. Returns null for
     * empty values, and for unparseable ones after logging a warning.
     */
    private function formatDate(mixed $value, string $context): ?string
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if (is_int($value)) {
            return date('Y-m-d', $value);
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            LogService::getInstance()->warning(
                'Sitemap: ignoring unparseable lastmod "' . $value . '" (' . $context . ').'
            );

            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
