<?php

namespace Neuedaten\Freezed\Services;

/**
 * Computes the cache-busting version of an asset.
 *
 * The version is a short hash of the file's *content*, not its modification
 * time: a build usually runs in CI, where "git clone" resets every mtime to the
 * checkout time. An mtime-based version would therefore invalidate every asset
 * on every deployment, even when nothing changed. A content hash only moves
 * when the file itself moves, which also keeps builds reproducible.
 *
 * Versions are appended as a query parameter (style.css?v=a1b2c3d4), so the
 * file keeps its name on disk and relative url() references inside CSS stay
 * intact. Processed images are the exception: freezed:image puts the hash into
 * the generated filename, because there it doubles as the cache key.
 */
class AssetVersionService
{
    /**
     * xxHash is part of ext-hash since PHP 8.1 and is an order of magnitude
     * faster than md5 on large media. crc32b is the fallback for exotic builds.
     */
    private const ALGO = 'xxh128';

    private const FALLBACK_ALGO = 'crc32b';

    /** Query parameter carrying the version. */
    private const PARAMETER = 'v';

    /**
     * Length of the truncated hash. The version is scoped to a single URL, so
     * the only harmful collision is the same file hashing equally in two
     * revisions; 32 bits are ample for that.
     */
    private const LENGTH = 8;

    /**
     * Short content hash of a file, or an empty string when the path does not
     * point at a readable file.
     */
    public static function hash(string $absolutePath): string
    {
        if ($absolutePath === '' || !is_file($absolutePath)) {
            return '';
        }

        $algorithm = in_array(self::ALGO, hash_algos(), true)
            ? self::ALGO
            : self::FALLBACK_ALGO;

        $hash = @hash_file($algorithm, $absolutePath);

        return $hash === false ? '' : substr($hash, 0, self::LENGTH);
    }

    /** Whether asset URLs from freezed:resource carry a version. */
    public static function isEnabled(): bool
    {
        return (bool) ConfigService::getInstance()->getValue('[assetVersioning]');
    }

    /**
     * Whether files from static/ carry a version too. Off by default, because
     * static/ exists to deliver stable URLs (robots.txt, .well-known/, domain
     * verification files).
     */
    public static function isEnabledForStatic(): bool
    {
        return self::isEnabled()
            && (bool) ConfigService::getInstance()->getValue('[assetVersioningStatic]');
    }

    /**
     * Version an asset URL. Returns the URL unchanged when there is no version,
     * so a missing or unreadable file degrades to the plain path.
     *
     * Must be called *after* FileService::virtualRealpath(), which splits on
     * directory separators and would mangle the query string.
     */
    public static function applyToUrl(string $url, string $version): string
    {
        if ($version === '') {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . self::PARAMETER . '=' . $version;
    }

    /** Version an asset URL by hashing the file it is served from. */
    public static function applyToUrlForFile(string $url, string $sourcePath): string
    {
        return self::applyToUrl($url, self::hash($sourcePath));
    }
}
