<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Domain\Model\ContentType;
use Neuedaten\Freezed\Domain\Repository\ContentRepository;
use Neuedaten\Freezed\Domain\Repository\ContentTypeRepository;

/**
 * Shared index of all content items plus URL helpers.
 *
 * The content repositories are created once per build and reused by the
 * compile loop, the sitemap and every ViewHelper that needs to look up an
 * item — so link-heavy templates never re-scan the content directory or
 * re-include variables.php files.
 *
 * Content references have the form "CONTENT:<contentType>/<itemFolder>",
 * e.g. "CONTENT:pages/impressum".
 */
class ContentUrlService
{
    public const REFERENCE_PREFIX = 'CONTENT:';

    protected static self|null $instance = null;

    /** @var array<int, ContentTypeRepository>|null */
    protected ?array $repositories = null;

    /** @var array<string, array<string, ContentType>>|null typeSlug => itemFolder => model */
    protected ?array $index = null;

    /** @var array<string, true> References already reported as dead during this build. */
    protected array $reportedDeadLinks = [];

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Forget all cached repositories and models so a subsequent build in the
     * same process starts from a clean state.
     */
    public function reset(): void
    {
        $this->repositories = null;
        $this->index = null;
        $this->reportedDeadLinks = [];
    }

    /**
     * All content type repositories, created once per build.
     *
     * @return array<int, ContentTypeRepository>
     * @throws \Exception When a content folder has no matching contentTypes config.
     */
    public function getRepositories(): array
    {
        if ($this->repositories === null) {
            $this->repositories = (new ContentRepository())->findAllContentTypeRepositories();
        }

        return $this->repositories;
    }

    public function getRepository(string $typeSlug): ?ContentTypeRepository
    {
        foreach ($this->getRepositories() as $repository) {
            if ($repository->getTypeSlug() === $typeSlug) {
                return $repository;
            }
        }

        return null;
    }

    /**
     * All items of all content types, in content type / directory order.
     *
     * @return array<int, ContentType>
     */
    public function getAllItems(): array
    {
        $items = [];
        foreach ($this->getRepositories() as $repository) {
            foreach ($repository->findAll() as $model) {
                $items[] = $model;
            }
        }

        return $items;
    }

    /**
     * Find a single item by content type slug and item folder name. Returns
     * null (never throws) when either does not exist.
     */
    public function findItem(string $typeSlug, string $itemFolder): ?ContentType
    {
        if ($this->index === null) {
            $this->index = [];
            foreach ($this->getAllItems() as $model) {
                $this->index[$model->getTypeSlug()][$model->getTitle()] = $model;
            }
        }

        return $this->index[$typeSlug][$itemFolder] ?? null;
    }

    public static function isReference(string $href): bool
    {
        return str_starts_with($href, self::REFERENCE_PREFIX);
    }

    /**
     * Split "CONTENT:pages/impressum" into ['pages', 'impressum']. Returns null
     * for malformed references.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function parseReference(string $reference): ?array
    {
        if (!self::isReference($reference)) {
            return null;
        }

        $body = trim(substr($reference, strlen(self::REFERENCE_PREFIX)), '/');
        $parts = explode('/', $body, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        return [$parts[0], $parts[1]];
    }

    public function resolveReference(string $reference): ?ContentType
    {
        $parts = self::parseReference($reference);

        return $parts === null ? null : $this->findItem($parts[0], $parts[1]);
    }

    /**
     * The configured public site URL without a trailing slash, or an empty
     * string when "siteUrl" is not set.
     */
    public function getSiteUrl(): string
    {
        $siteUrl = ConfigService::getInstance()->getValue('[siteUrl]');

        return is_string($siteUrl) ? rtrim(trim($siteUrl), '/') : '';
    }

    /**
     * Public URL of a content item, optionally absolute and with a fragment.
     */
    public function getUrl(ContentType $model, bool $absolute = false, ?string $section = null): string
    {
        return $this->decorate($model->getPublicPath(), $absolute, $section);
    }

    /**
     * Apply the siteUrl prefix and/or a "#section" fragment to any URL.
     * Absolute URLs (scheme or protocol-relative) are never prefixed.
     */
    public function decorate(string $url, bool $absolute = false, ?string $section = null): string
    {
        if ($absolute && !self::isAbsoluteUrl($url)) {
            $siteUrl = $this->getSiteUrl();
            if ($siteUrl !== '') {
                $url = $siteUrl . '/' . ltrim($url, '/');
            } else {
                LogService::getInstance()->warning(
                    'Cannot build an absolute URL for "' . $url . '": "siteUrl" is not set in freezed.config.php.'
                );
            }
        }

        if ($section !== null && $section !== '') {
            $url .= '#' . ltrim($section, '#');
        }

        return $url;
    }

    /**
     * True for URLs with a scheme (https:, mailto:, tel:, …) or a
     * protocol-relative "//host" prefix. Note that "CONTENT:" references match
     * this pattern too, so check isReference() first.
     */
    public static function isAbsoluteUrl(string $url): bool
    {
        return (bool) preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $url);
    }

    /**
     * Returns true the first time a dead reference is reported in this build,
     * false on every later call — so a broken link in a shared partial is
     * warned about once instead of once per page.
     */
    public function markDeadLinkReported(string $reference): bool
    {
        if (isset($this->reportedDeadLinks[$reference])) {
            return false;
        }

        $this->reportedDeadLinks[$reference] = true;

        return true;
    }
}
