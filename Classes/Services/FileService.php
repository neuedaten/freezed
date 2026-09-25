<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Domain\Model\Resource;
use Neuedaten\Freezed\Exception\PathNotAllowedException;

/**
 * File system access for the build.
 *
 * One rule applies to every file a build touches: it lies below the project
 * directory or below one of the declared roots (see ProjectPathsService).
 *
 *   - Reads go through assertAllowedPath(): the real path of a file must be
 *     inside the project, the content, theme or static directory, or an
 *     asset root. A symlink below any of them that points elsewhere is
 *     refused.
 *   - Writes go through resolveOutputPath(): the target is normalised on
 *     paper and must stay below public/ (or the image cache), whatever a
 *     targetFileName or a configured sub-directory says.
 *   - Deletes never follow a symlink (the link itself is removed, its target
 *     is left alone) and only ever run inside the directory that is being
 *     emptied, which is never the project itself.
 */
class FileService
{

    /**
     * File names that are never part of a site and are therefore not copied:
     * desktop metadata the operating system writes into every folder it opens.
     */
    private const IGNORED_FILE_NAMES = ['.DS_Store', 'Thumbs.db'];

    protected string $targetDirectory;

    public function __construct()
    {
        $this->targetDirectory = ProjectPathsService::getInstance()->getRealPath('publicPath');
    }

    /**
     * Empty the public directory, creating it when it does not exist yet. A
     * .git directory directly inside it is kept, see deleteContents().
     */
    public function clearTargetDirectory(): void
    {
        if (!is_dir($this->targetDirectory)) {
            $this->createDirectoryIfNotExist($this->targetDirectory);
            return;
        }

        $this->clearDirectoryContent($this->targetDirectory, true);
    }

    /**
     * Empty a directory below public/ or below the image cache. Anything else
     * is refused, the project directory in particular.
     */
    public function clearDirectoryContent(
        string $path,
        bool $recursive = true
    ): void {
        $real = realpath($path);
        if (!$real || !is_dir($real)) {
            throw new PathNotAllowedException('Cannot empty "' . $path . '": it is not a directory.');
        }

        $paths = ProjectPathsService::getInstance();
        $allowed = [$paths->getRealPath('publicPath'), $paths->getRealPath('imageCacheDirectory')];

        $inside = false;
        foreach ($allowed as $root) {
            if (self::isInside($real, $root)) {
                $inside = true;
            }
        }

        if (!$inside || $real === $paths->getProjectRoot()) {
            throw new PathNotAllowedException(
                'Refusing to empty "' . $real . '": only the public directory and the image cache are ever emptied.'
            );
        }

        $this->deleteContents($real, $recursive);
    }

    /**
     * Delete everything inside a directory without ever following a symlink:
     * a link is unlinked, whatever it points to stays untouched. A .git
     * directory directly inside the directory is kept, because it belongs to
     * a deployment setup and deleting it would be unrecoverable.
     */
    private function deleteContents(string $directory, bool $recursive): void
    {
        foreach (self::directoryItems($directory) as $file) {
            if (basename($file) === '.git') {
                continue;
            }

            if (is_link($file) || is_file($file)) {
                unlink($file);
                LogService::getInstance()->add('Delete file: ' . $file,
                    LogService::TYPES['info']);
                continue;
            }

            if ($recursive && is_dir($file)) {
                $this->deleteContents($file, true);
                rmdir($file);
                LogService::getInstance()->add('Delete directory: ' . $file,
                    LogService::TYPES['info']);
            }
        }
    }

    /**
     * True when a file with this path (relative to the public directory)
     * already exists in the current build output.
     */
    public function fileExists(string $path): bool
    {
        return is_file($this->targetDirectory . '/' . $path);
    }

    /**
     * Write a file below the public directory. $path is relative to it and
     * may not leave it.
     *
     * @throws PathNotAllowedException
     */
    public function writeFile(string $path, string $content): void
    {
        $target = $this->resolveOutputPath($path, 'Output file "' . $path . '"');

        // Ensure the target subdirectory exists (e.g. public/cases/ for a
        // content type with a non-empty targetDirectory).
        $this->createDirectoryIfNotExist(dirname($target));
        file_put_contents($target, $content);
        LogService::getInstance()->add('Write file: ' . $target,
            LogService::TYPES['info']);
    }

    public function copyResource(Resource $resource): void
    {
        $relative = implode('/', [
            (string) ConfigService::getInstance()->getValue('[assetsDirectory]'),
            $resource->getTargetPath(),
        ]);

        $targetPath = $this->resolveOutputPath($relative, 'Resource target "' . $relative . '"');

        $this->createDirectoryIfNotExist(dirname($targetPath));

        copy($resource->getSourcePath(), $targetPath);
        LogService::getInstance()->add('Copy resource: '
            . $resource->getSourcePath() . ' to ' . $targetPath,
            LogService::TYPES['info']);
    }

    /**
     * Absolute path of an output file below the public directory, or an
     * exception when the relative path would leave it.
     *
     * @throws PathNotAllowedException
     */
    public function resolveOutputPath(string $relativePath, string $description): string
    {
        return self::resolvePathBelow($this->targetDirectory, $relativePath, $description);
    }

    /**
     * Join a relative path to a root and make sure the result stays strictly
     * below the root -- on paper, and, once the parent directory exists, on
     * disk as well, so no symlink inside the root can redirect the write.
     *
     * @throws PathNotAllowedException
     */
    public static function resolvePathBelow(string $root, string $relativePath, string $description): string
    {
        $root = rtrim($root, '/\\');
        $target = self::virtualRealpath($root . '/' . ltrim(str_replace('\\', '/', $relativePath), '/'));

        if ($target === $root || !self::isInside($target, $root)) {
            throw new PathNotAllowedException(sprintf(
                '%s resolves to "%s", outside "%s". Freezed only writes below the public directory and the image cache.',
                $description,
                $target,
                $root
            ));
        }

        // Directories that exist already between the root and the target
        // must really be below the root; a symlink there would redirect the
        // write. Missing directories are created fresh, so there is nothing
        // to check for them (nor when the root itself does not exist yet).
        $parent = self::nearestExistingAncestor(dirname($target));
        if ($parent !== $root && self::isInside($parent, $root)) {
            $parentReal = realpath($parent);
            $rootReal = realpath($root) ?: $root;
            if ($parentReal !== false && !self::isInside($parentReal, $rootReal)) {
                throw new PathNotAllowedException(sprintf(
                    '%s would be written through "%s", which leads outside "%s".',
                    $description,
                    $parent,
                    $rootReal
                ));
            }
        }

        return $target;
    }

    private static function nearestExistingAncestor(string $path): string
    {
        while (!file_exists($path) && dirname($path) !== $path) {
            $path = dirname($path);
        }

        return $path;
    }

    private function createDirectoryIfNotExist(string $path): void
    {
        $permissions = ConfigService::getInstance()
            ->getValue('[mkdirPermissions]');

        if (!is_dir($path)) {
            mkdir($path, $permissions, true);
        }
    }

    /**
     * Copy a directory verbatim, including dot files and dot directories
     * (.htaccess, .well-known/), which is what static/ is for.
     *
     * Symlinks are copied as what they point to when the target lies inside
     * the project or an asset root, and skipped with a warning otherwise. A
     * directory is never entered twice, so a link back to an ancestor cannot
     * loop.
     *
     * @param array<string, true> $visited Real paths of directories already copied.
     */
    public function copyDirectoryItems(string $source, string $target, array $visited = []): void
    {
        $source = self::virtualRealpath($source);
        $target = self::virtualRealpath($target);

        $sourceReal = realpath($source);
        if ($sourceReal === false) {
            return;
        }
        if (isset($visited[$sourceReal])) {
            return;
        }
        $visited[$sourceReal] = true;

        $this->createDirectoryIfNotExist($target);

        // A directory that contains its own copy target (e.g. a symlink in
        // static/ that points at the project root) would be copied into
        // itself without end.
        $targetReal = realpath($target);
        if ($targetReal !== false && self::isInside($targetReal, $sourceReal)) {
            LogService::getInstance()->warning(
                'Skipped ' . $source . ': it contains the target directory ' . $targetReal . '.'
            );
            return;
        }

        foreach (self::directoryItems($source) as $file) {
            if (in_array(basename($file), self::IGNORED_FILE_NAMES, true)) {
                continue;
            }

            if (is_link($file)) {
                $linkTarget = realpath($file);
                if ($linkTarget === false || !self::isAllowedPath($linkTarget)) {
                    LogService::getInstance()->warning(
                        'Skipped symlink ' . $file . ': it points outside the project ('
                        . ($linkTarget === false ? 'dangling' : $linkTarget) . ').'
                    );
                    continue;
                }
            }

            if (is_file($file)) {
                $targetFile = $target . '/' . basename($file);
                copy($file, $targetFile);
                LogService::getInstance()->add('Copy file: ' . $file . ' to '
                    . $targetFile, LogService::TYPES['info']);
            }
            if (is_dir($file)) {
                $targetDirectory = $target . '/' . basename($file);
                $this->copyDirectoryItems($file, $targetDirectory, $visited);
            }
        }
    }

    public function copyFileIfTargetNotExists(
        string $source,
        string $target
    ): void {
        $source = realpath($source);
        $target = self::virtualRealpath($target);

        if (!$source || realpath($target)) {
            return;
        }

        if (!is_file($target)) {
            copy($source, $target);
            LogService::getInstance()->add('Copy file: ' . $source . ' to '
                . $target, LogService::TYPES['info']);
        }
    }


    /**
     * Absolute paths of everything in a directory, dot files included -- unlike
     * glob(), which skips them unless the pattern starts with a dot.
     *
     * @return string[]
     */
    private static function directoryItems(string $path): array
    {
        $entries = @scandir($path);
        if ($entries === false) {
            return [];
        }

        $items = [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $items[] = $path . DIRECTORY_SEPARATOR . $entry;
        }

        return $items;
    }

    static function virtualRealpath($path): string
    {
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $resultPathParts = [];
        foreach ($parts as $part) {
            if ('.' == $part) {
                continue;
            }
            if ('..' == $part) {
                array_pop($resultPathParts);
            } else {
                $resultPathParts[] = $part;
            }
        }

        foreach ($resultPathParts as $index => $part) {
            if ($part === '' && $index > 0) {
                unset($resultPathParts[$index]);
            }
        }

        return implode(DIRECTORY_SEPARATOR, $resultPathParts);
    }

    /**
     * The path relative to the root it belongs to, with a leading slash:
     * "/00_default/assets/css/main.css" for a theme file,
     * "/pages/home/assets/hero.jpg" for a content file and
     * "/media/2026/terrace.jpg" for a file below the assetRoots entry "media".
     * A path below none of them is returned unchanged.
     */
    static function getPathWithoutThemeOrContentDirectory(string $path): string
    {
        $paths = ProjectPathsService::getInstance();

        // Paths arrive both as configured and as real paths, so match against
        // both spellings of each root.
        foreach (['themesPath', 'contentPath'] as $key) {
            $lexical = $paths->getLexicalPath($key);
            foreach (array_unique([$lexical, realpath($lexical) ?: $lexical]) as $candidate) {
                if (self::isInside($path, $candidate)) {
                    return substr($path, strlen(rtrim($candidate, '/\\')));
                }
            }
        }

        $assetRoot = AssetRootService::getInstance()->relativize($path);
        if ($assetRoot !== null) {
            return '/' . $assetRoot[0] . str_replace('\\', '/', $assetRoot[1]);
        }

        return $path;
    }

    /**
     * True when an SVG file contains something that runs or embeds code when
     * the file is opened in a browser: a <script> element, an event handler
     * attribute (onload="…"), a javascript: URL or a <foreignObject>. Such a
     * file is not published by freezed:image or freezed:resource, because an
     * SVG served from the site's own origin runs with the site's rights.
     *
     * This is a pattern check that catches the plain cases, not a sanitiser.
     */
    public static function svgContainsScript(string $realPath): bool
    {
        if (strtolower(pathinfo($realPath, PATHINFO_EXTENSION)) !== 'svg') {
            return false;
        }

        $content = @file_get_contents($realPath);
        if ($content === false) {
            return false;
        }

        return (bool) preg_match(
            '/<\s*script\b|<\s*foreignObject\b|\bon[a-z]+\s*=|javascript\s*:|<\s*set\b[^>]*attributeName\s*=\s*["\']?on/i',
            $content
        );
    }

    /**
     * Real path of the project root.
     */
    public static function getProjectRootRealPath(): string
    {
        $projectRoot = (string) ConfigService::getInstance()->getValue('[projectRoot]');

        return realpath($projectRoot) ?: rtrim($projectRoot, '/\\');
    }

    /**
     * True for "/etc/passwd", "C:\data" and "\\server\share".
     */
    public static function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }

    /**
     * True when $path is $root itself or lies below it. Both are compared as
     * given, so pass real paths (or two lexically normalised paths).
     */
    public static function isInside(string $path, string $root): bool
    {
        $root = rtrim($root, '/\\');

        return $path === $root
            || str_starts_with($path, $root . '/')
            || str_starts_with($path, $root . '\\');
    }

    /**
     * True when a real path lies inside the project or one of the declared
     * read roots (content, themes, static, asset roots).
     */
    public static function isAllowedPath(string $realPath): bool
    {
        foreach (ProjectPathsService::getInstance()->getReadRoots() as $root) {
            if (self::isInside($realPath, $root)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Make sure a real path lies inside the project or one of the declared
     * read roots, and throw otherwise.
     *
     * @param string $realPath    The resolved (realpath) file or directory.
     * @param string $description What the path is, for the error message,
     *                            e.g. 'freezed:image src "…"'.
     *
     * @throws PathNotAllowedException
     */
    public static function assertAllowedPath(string $realPath, string $description): void
    {
        if (self::isAllowedPath($realPath)) {
            return;
        }

        throw new PathNotAllowedException(sprintf(
            '%s resolves to "%s", outside the project directory. Freezed only reads files below the project root, its content, theme and static directories and the configured assetRoots.',
            $description,
            $realPath
        ));
    }

}
