<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Exception\PathNotAllowedException;

/**
 * Named directories that templates may read files from, in addition to the
 * content folder and the themes:
 *
 *     'assetRoots' => [
 *         'media' => 'data/media',
 *     ],
 *
 * A root is addressed by its name as the "context" of freezed:image and
 * freezed:resource, with paths relative to it:
 *
 *     {freezed:image(src: '2026/09/terrace.jpg', context: 'media', width: 800)}
 *     {freezed:resource(path: 'brochure.pdf', context: 'media')}
 *
 * Output paths carry the root's name: public/images/media/2026/09/terrace_….webp
 * for the image above, public/media/brochure.pdf for the resource.
 *
 * Every root is given relative to the project root and must stay inside it.
 * A root may be a symlink to a folder elsewhere (data -> ../shared-data): the
 * symlink is the deliberate decision, made in the project, that this folder
 * belongs to the site. Files are then checked against the real path of the
 * root, so a path cannot leave it through "..".
 */
class AssetRootService
{
    /** Context names with a fixed meaning that a root cannot take. */
    public const RESERVED_NAMES = ['theme', 'static', 'content'];

    protected static self|null $instance = null;

    /** @var array<string, string>|null Root name => real path, once validated. */
    protected ?array $roots = null;

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Forget the validated roots so a later build in the same process re-reads
     * the configuration.
     */
    public function reset(): void
    {
        $this->roots = null;
    }

    /**
     * All configured roots as name => real path.
     *
     * @return array<string, string>
     * @throws PathNotAllowedException For a root that is misconfigured.
     */
    public function getRoots(): array
    {
        if ($this->roots !== null) {
            return $this->roots;
        }

        $configured = ConfigService::getInstance()->getValue('[assetRoots]') ?? [];
        if (!is_array($configured)) {
            throw new PathNotAllowedException('"assetRoots" in freezed.config.php must be an array of name => path.');
        }

        $projectRoot = FileService::getProjectRootRealPath();
        $roots = [];

        foreach ($configured as $name => $path) {
            $name = (string) $name;

            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $name)) {
                throw new PathNotAllowedException(sprintf(
                    'assetRoots: "%s" is not a valid root name (letters, digits, "-" and "_" only).',
                    $name
                ));
            }

            if (in_array(strtolower($name), self::RESERVED_NAMES, true)) {
                throw new PathNotAllowedException(sprintf(
                    'assetRoots: "%s" is a reserved context name and cannot be used as a root.',
                    $name
                ));
            }

            if (!is_string($path) || trim($path) === '') {
                throw new PathNotAllowedException(sprintf('assetRoots: the path of root "%s" must be a non-empty string.', $name));
            }

            if (FileService::isAbsolutePath($path)) {
                throw new PathNotAllowedException(sprintf(
                    'assetRoots: root "%s" is given as the absolute path "%s". Roots are relative to the project root and must lie inside it; use a symlink inside the project for a folder elsewhere.',
                    $name,
                    $path
                ));
            }

            // Lexical check first: "../shared" leaves the project on paper,
            // no matter where it would point on disk.
            $lexical = FileService::virtualRealpath($projectRoot . '/' . $path);
            if (!FileService::isInside($lexical, $projectRoot)) {
                throw new PathNotAllowedException(sprintf(
                    'assetRoots: root "%s" ("%s") lies outside the project directory. Roots must be inside the project; use a symlink for a folder elsewhere.',
                    $name,
                    $path
                ));
            }

            $real = realpath($lexical);
            if ($real === false || !is_dir($real)) {
                throw new PathNotAllowedException(sprintf(
                    'assetRoots: root "%s" ("%s") is not a directory (looked for %s).',
                    $name,
                    $path,
                    $lexical
                ));
            }

            $roots[$name] = $real;
        }

        return $this->roots = $roots;
    }

    public function hasRoot(string $name): bool
    {
        return isset($this->getRoots()[$name]);
    }

    /**
     * Real path of the root with the given name.
     *
     * @throws PathNotAllowedException For an unknown root.
     */
    public function getRoot(string $name): string
    {
        $roots = $this->getRoots();

        if (!isset($roots[$name])) {
            throw new PathNotAllowedException(sprintf(
                'Unknown context "%s". Use "theme", "static" or one of the configured assetRoots%s.',
                $name,
                $roots === [] ? ' (none configured)' : ': ' . implode(', ', array_keys($roots))
            ));
        }

        return $roots[$name];
    }

    /**
     * Real path of a file below a root, or null when the file does not exist.
     *
     * @throws PathNotAllowedException When the root is unknown, the path is
     *         absolute, or the file resolves to a place outside the root.
     */
    public function resolveFile(string $rootName, string $relativePath): ?string
    {
        $root = $this->getRoot($rootName);

        if (FileService::isAbsolutePath($relativePath)) {
            throw new PathNotAllowedException(sprintf(
                'Path "%s" for context "%s" must be relative to that root, not absolute.',
                $relativePath,
                $rootName
            ));
        }

        $resolved = realpath($root . '/' . $relativePath);
        if ($resolved === false) {
            return null;
        }

        if (!FileService::isInside($resolved, $root)) {
            throw new PathNotAllowedException(sprintf(
                'Path "%s" leaves the root "%s" (%s).',
                $relativePath,
                $rootName,
                $root
            ));
        }

        return $resolved;
    }

    /**
     * The root a real path belongs to, as [name, path relative to the root
     * with a leading slash], or null when the path is below no root.
     *
     * @return array{0: string, 1: string}|null
     */
    public function relativize(string $realPath): ?array
    {
        foreach ($this->getRoots() as $name => $root) {
            if (FileService::isInside($realPath, $root)) {
                return [$name, substr($realPath, strlen($root))];
            }
        }

        return null;
    }
}
