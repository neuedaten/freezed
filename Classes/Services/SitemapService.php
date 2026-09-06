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
 *         'lastmod' => null,   // fallback for items without their own lastmod
 *     ],
 *
 * Per item (variables.php):
 *   'lastmod' => '2026-01-31'   // any strtotime()-parseable value or DateTimeInterface
 *   'sitemap' => false          // exclude this item
 */
class SitemapService
{
    public const FILE_NAME = 'sitemap.xml';

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

        $fileService->writeFile(self::FILE_NAME, $this->generate($config['lastmod']));

        return true;
    }

    /**
     * Build the sitemap XML for all content items.
     *
     * @param mixed $defaultLastmod Fallback lastmod for items without their own.
     */
    public function generate(mixed $defaultLastmod = null): string
    {
        $urlService = ContentUrlService::getInstance();

        $siteUrl = $urlService->getSiteUrl();
        if ($siteUrl === '') {
            LogService::getInstance()->warning(
                'Sitemap: "siteUrl" is not set in freezed.config.php, writing root-relative <loc> entries. '
                . 'Set siteUrl (e.g. https://example.com) so search engines can use the sitemap.'
            );
        }

        $defaultDate = $this->formatDate($defaultLastmod, 'sitemap.lastmod in freezed.config.php');

        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($urlService->getAllItems() as $model) {
            if (!$this->isIncluded($model)) {
                continue;
            }

            $loc = $siteUrl !== '' ? $urlService->getUrl($model, true) : $model->getPublicPath();

            $itemLastmod = $model->getVariables()['lastmod'] ?? null;
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
     * @return array{enabled: bool, lastmod: mixed}
     */
    private function getConfig(): array
    {
        $raw = ConfigService::getInstance()->getValue('[sitemap]');

        if ($raw === true) {
            return ['enabled' => true, 'lastmod' => null];
        }

        if (!is_array($raw)) {
            return ['enabled' => false, 'lastmod' => null];
        }

        return [
            'enabled' => (bool) ($raw['enabled'] ?? false),
            'lastmod' => $raw['lastmod'] ?? null,
        ];
    }

    /**
     * An item is excluded when its variables set 'sitemap' => false.
     */
    private function isIncluded(ContentType $model): bool
    {
        $variables = $model->getVariables();

        return !(array_key_exists('sitemap', $variables) && $variables['sitemap'] === false);
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
