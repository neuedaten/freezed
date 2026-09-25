<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Exception\PathNotAllowedException;

/**
 * The directories a build may touch, and the rules that keep every read and
 * write inside the project.
 *
 * Declared roots are the directories named in the configuration: contentPath,
 * themesPath, staticPath, publicPath, imageCacheDirectory and the assetRoots.
 * Each must be given relative to the project root and lie inside it on paper.
 * A declared root may be a symlink to a folder elsewhere -- that link is a
 * decision made in the project -- and its real path then counts as the root.
 * Everything below a root, however, must resolve inside the project or one of
 * the roots: a symlink further down that points outside is refused. That is
 * the one rule for content items, themes, static files, images and resources.
 *
 * validate() runs before any command touches the file system. It refuses a
 * configuration under which a build would read from, write to or delete
 * anything outside the project, or would delete the project itself:
 *
 *   - a root that is absolute, empty, or leaves the project ("../shared");
 *   - publicPath or imageCacheDirectory equal to the project root, or
 *     overlapping content, themes, static, each other or an asset root
 *     (the build empties both directories);
 *   - assetsDirectory, imagePublicDirectory, a content type's targetDirectory
 *     or a theme sub-path that climbs upwards with "..".
 */
class ProjectPathsService
{
    /** Configuration keys of the declared directories, relative to the project root. */
    public const DIRECTORY_KEYS = ['contentPath', 'themesPath', 'staticPath', 'publicPath', 'imageCacheDirectory'];

    /** Keys whose directory is emptied by a build (or by cache:flush). */
    public const CLEARED_KEYS = ['publicPath', 'imageCacheDirectory'];

    /** Sub-paths below public/; they may only descend and are never absolute. */
    public const OUTPUT_SUB_PATH_KEYS = ['assetsDirectory', 'imagePublicDirectory'];

    /** Sub-paths joined to a theme folder; written with a leading slash by convention. */
    public const THEME_SUB_PATH_KEYS = [
        'themeTemplatesPath',
        'themeLayoutsPath',
        'themePartialsPath',
        'themeComponentsPath',
        'themeStaticPath',
    ];

    protected static self|null $instance = null;

    protected bool $validated = false;

    /** @var array<string, string>|null Key => lexical absolute path. */
    protected ?array $lexical = null;

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function reset(): void
    {
        $this->validated = false;
        $this->lexical = null;
    }

    /**
     * Real path of the project root.
     */
    public function getProjectRoot(): string
    {
        return FileService::getProjectRootRealPath();
    }

    /**
     * Check the configuration once per process.
     *
     * @throws PathNotAllowedException
     */
    public function validate(): void
    {
        if ($this->validated) {
            return;
        }

        $config = ConfigService::getInstance();
        $projectRoot = $this->getProjectRoot();

        if (!is_dir($projectRoot)) {
            throw new PathNotAllowedException('Project root "' . $projectRoot . '" is not a directory.');
        }

        $lexical = [];
        foreach (self::DIRECTORY_KEYS as $key) {
            $lexical[$key] = $this->validateDirectoryKey($key, $config->getValue('[' . $key . ']'), $projectRoot);
        }

        $this->lexical = $lexical;

        // The directories a build empties must not reach any other directory
        // the build depends on, neither on paper nor through a symlink.
        foreach (self::CLEARED_KEYS as $clearedKey) {
            foreach (self::DIRECTORY_KEYS as $otherKey) {
                if ($otherKey === $clearedKey) {
                    continue;
                }
                $this->assertNoOverlap($clearedKey, $lexical[$clearedKey], $otherKey, $lexical[$otherKey]);
            }

            foreach (AssetRootService::getInstance()->getRoots() as $name => $realRoot) {
                $this->assertNoOverlap($clearedKey, $lexical[$clearedKey], 'assetRoots.' . $name, $realRoot);
            }
        }

        foreach (self::OUTPUT_SUB_PATH_KEYS as $key) {
            $this->validateSubPath($key, $config->getValue('[' . $key . ']'), false);
        }

        foreach (self::THEME_SUB_PATH_KEYS as $key) {
            $this->validateSubPath($key, $config->getValue('[' . $key . ']'), true);
        }

        $contentTypes = $config->getValue('[contentTypes]') ?? [];
        if (is_array($contentTypes)) {
            foreach ($contentTypes as $typeSlug => $typeConfig) {
                if (is_array($typeConfig) && array_key_exists('targetDirectory', $typeConfig)) {
                    $this->validateSubPath('contentTypes.' . $typeSlug . '.targetDirectory', $typeConfig['targetDirectory'], false);
                }
            }
        }

        $this->validated = true;
    }

    /**
     * Lexical absolute path of a declared directory (it need not exist yet).
     */
    public function getLexicalPath(string $key): string
    {
        $this->validate();

        return $this->lexical[$key];
    }

    /**
     * Real path of a declared directory, or its lexical path while it does
     * not exist yet (public/ and the cache are created on demand).
     */
    public function getRealPath(string $key): string
    {
        $lexical = $this->getLexicalPath($key);

        return realpath($lexical) ?: $lexical;
    }

    /**
     * The real paths a build may read files from: the project itself, the
     * declared content, theme and static directories (relevant when one of
     * them is a symlink) and the asset roots.
     *
     * @return array<int, string>
     */
    public function getReadRoots(): array
    {
        $this->validate();

        $roots = [$this->getProjectRoot()];

        foreach (['contentPath', 'themesPath', 'staticPath'] as $key) {
            $real = realpath($this->lexical[$key]);
            if ($real !== false) {
                $roots[] = $real;
            }
        }

        foreach (AssetRootService::getInstance()->getRoots() as $real) {
            $roots[] = $real;
        }

        return array_values(array_unique($roots));
    }

    private function validateDirectoryKey(string $key, mixed $value, string $projectRoot): string
    {
        if (!is_string($value) || trim($value, '/\\ ') === '') {
            throw new PathNotAllowedException(sprintf(
                '"%s" in freezed.config.php must be a non-empty path relative to the project root.',
                $key
            ));
        }

        if (FileService::isAbsolutePath($value)) {
            throw new PathNotAllowedException(sprintf(
                '"%s" in freezed.config.php is the absolute path "%s". Declared directories are relative to the project root and must lie inside it; use a symlink inside the project for a folder elsewhere.',
                $key,
                $value
            ));
        }

        $lexical = FileService::virtualRealpath($projectRoot . '/' . $value);

        if ($lexical === $projectRoot || !FileService::isInside($lexical, $projectRoot)) {
            throw new PathNotAllowedException(sprintf(
                '"%s" => "%s" in freezed.config.php is the project directory itself or lies outside it. Declared directories must be sub-directories of the project.',
                $key,
                $value
            ));
        }

        return $lexical;
    }

    /**
     * Two directories overlap when one is the other or lies inside it. Checked
     * on the paths as written and on the real paths, so a symlinked public/
     * cannot point into the content directory either.
     */
    private function assertNoOverlap(string $keyA, string $pathA, string $keyB, string $pathB): void
    {
        $candidatesA = array_unique([$pathA, realpath($pathA) ?: $pathA]);
        $candidatesB = array_unique([$pathB, realpath($pathB) ?: $pathB]);

        foreach ($candidatesA as $a) {
            foreach ($candidatesB as $b) {
                if (FileService::isInside($a, $b) || FileService::isInside($b, $a)) {
                    throw new PathNotAllowedException(sprintf(
                        '"%s" (%s) overlaps "%s" (%s) in freezed.config.php. The build empties %s, so it must not contain or lie inside another directory of the project.',
                        $keyA,
                        $pathA,
                        $keyB,
                        $pathB,
                        $keyA
                    ));
                }
            }
        }
    }

    /**
     * A sub-path may only descend: no "..", no drive letter, no UNC prefix.
     * Output sub-paths must be relative as well; theme sub-paths are written
     * with a leading slash by convention ('/templates/layouts/').
     */
    private function validateSubPath(string $key, mixed $value, bool $allowLeadingSlash): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $segments = is_string($value) ? explode('/', str_replace('\\', '/', $value)) : null;

        $invalid = $segments === null
            || in_array('..', $segments, true)
            || preg_match('#^[A-Za-z]:#', $value)
            || str_starts_with($value, '//')
            || str_starts_with($value, '\\')
            || (!$allowLeadingSlash && str_starts_with($value, '/'));

        if ($invalid) {
            throw new PathNotAllowedException(sprintf(
                '"%s" => "%s" in freezed.config.php must be a relative path that only descends (no leading slash, no "..", no drive letter).',
                $key,
                is_string($value) ? $value : get_debug_type($value)
            ));
        }
    }
}
