<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Domain\Source\ContentSourceInterface;
use Neuedaten\Freezed\Domain\Source\DirectoryContentSource;
use Neuedaten\Freezed\Domain\Source\JsonContentSource;
use Neuedaten\Freezed\Domain\Source\ScriptContentSource;
use Neuedaten\Freezed\Exception\ContentSourceException;

/**
 * Turns the "source" setting of a content type into a ContentSourceInterface
 * and keeps one instance per source, so content types that share a source
 * (e.g. one class for entries, spots and categories) share its state, such
 * as a database connection.
 *
 * Accepted values for contentTypes.<type>.source:
 *
 *   (none) / 'directory'      Folders below content/<type>/ (the default).
 *   'App\Content\MySource'    A class implementing ContentSourceInterface,
 *                             autoloaded through the project's composer.json.
 *   'data/entries.php'        A PHP script returning the items (or a source
 *                             object), path relative to the project root.
 *   'data/entries.json'       A JSON file with the items.
 *   an object                 Any ContentSourceInterface instance.
 *
 * Script and JSON paths must lie inside the project directory.
 */
class ContentSourceService
{
    public const DIRECTORY = 'directory';

    protected static self|null $instance = null;

    /** @var array<string, ContentSourceInterface> Keyed by class name or resolved file path. */
    protected array $sources = [];

    /** @var array<string, true> getVersion() failures already reported by the watcher. */
    protected array $reportedVersionErrors = [];

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * The source that supplies the items of a content type.
     *
     * @throws ContentSourceException For a "source" value that cannot be resolved.
     */
    public function getSource(string $typeSlug, array $contentTypeConfig): ContentSourceInterface
    {
        $source = $contentTypeConfig['source'] ?? null;

        if ($source instanceof ContentSourceInterface) {
            return $source;
        }

        if ($source === null || $source === '' || $source === self::DIRECTORY) {
            return $this->sources[self::DIRECTORY] ??= new DirectoryContentSource();
        }

        if (!is_string($source)) {
            throw new ContentSourceException(sprintf(
                'Content type "%s": "source" must be a class name, a file path or a %s, got %s.',
                $typeSlug,
                ContentSourceInterface::class,
                get_debug_type($source)
            ));
        }

        if ($this->looksLikeFilePath($source)) {
            return $this->getFileSource($typeSlug, $source);
        }

        return $this->getClassSource($typeSlug, $source);
    }

    /**
     * True when a content type reads its items from somewhere other than
     * its folder.
     */
    public static function hasCustomSource(array $contentTypeConfig): bool
    {
        $source = $contentTypeConfig['source'] ?? null;

        return $source !== null && $source !== '' && $source !== self::DIRECTORY;
    }

    /**
     * Current version of every content type with a custom source, keyed by
     * type slug, for the file watcher. Types whose source reports no version
     * are left out. A failing getVersion() is logged once and skipped, so a
     * broken source does not kill the watch loop.
     *
     * @return array<string, string>
     */
    public function getVersions(): array
    {
        $contentTypes = ConfigService::getInstance()->getValue('[contentTypes]') ?? [];
        $versions = [];

        foreach ($contentTypes as $typeSlug => $contentTypeConfig) {
            if (!is_array($contentTypeConfig) || !self::hasCustomSource($contentTypeConfig)) {
                continue;
            }

            try {
                $version = $this->getSource((string) $typeSlug, $contentTypeConfig)
                    ->getVersion((string) $typeSlug, $contentTypeConfig);
            } catch (\Throwable $exception) {
                $message = 'Content source of "' . $typeSlug . '" failed to report a version: ' . $exception->getMessage();
                if (!isset($this->reportedVersionErrors[$message])) {
                    $this->reportedVersionErrors[$message] = true;
                    LogService::getInstance()->warning($message);
                }
                continue;
            }

            if ($version !== null) {
                $versions[(string) $typeSlug] = $version;
            }
        }

        return $versions;
    }

    private function getClassSource(string $typeSlug, string $className): ContentSourceInterface
    {
        $className = ltrim($className, '\\');

        if (isset($this->sources[$className])) {
            return $this->sources[$className];
        }

        if (!class_exists($className)) {
            throw new ContentSourceException(sprintf(
                'Content type "%s": source class "%s" not found. Check the class name and the autoload section of your composer.json.',
                $typeSlug,
                $className
            ));
        }

        if (!is_subclass_of($className, ContentSourceInterface::class)) {
            throw new ContentSourceException(sprintf(
                'Content type "%s": source class "%s" must implement %s.',
                $typeSlug,
                $className,
                ContentSourceInterface::class
            ));
        }

        return $this->sources[$className] = new $className();
    }

    private function getFileSource(string $typeSlug, string $path): ContentSourceInterface
    {
        if (FileService::isAbsolutePath($path)) {
            throw new ContentSourceException(sprintf(
                'Content type "%s": source path "%s" must be relative to the project root.',
                $typeSlug,
                $path
            ));
        }

        $projectRoot = (string) ConfigService::getInstance()->getValue('[projectRoot]');
        $resolved = realpath($projectRoot . '/' . $path);

        if ($resolved === false || !is_file($resolved)) {
            throw new ContentSourceException(sprintf(
                'Content type "%s": source file "%s" not found (looked for %s).',
                $typeSlug,
                $path,
                $projectRoot . '/' . $path
            ));
        }

        FileService::assertAllowedPath($resolved, 'content source of "' . $typeSlug . '"');

        if (isset($this->sources[$resolved])) {
            return $this->sources[$resolved];
        }

        $extension = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));

        return $this->sources[$resolved] = $extension === 'json'
            ? new JsonContentSource($resolved)
            : new ScriptContentSource($resolved);
    }

    /**
     * "data/entries.php" and "./data.json" are paths; "App\Content\Source"
     * is a class name.
     */
    private function looksLikeFilePath(string $source): bool
    {
        if (str_contains($source, '\\')) {
            return false;
        }

        return str_contains($source, '/')
            || (bool) preg_match('/\.(php|json)$/i', $source);
    }
}
