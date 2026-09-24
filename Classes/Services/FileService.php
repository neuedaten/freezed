<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Domain\Model\Resource;
use Neuedaten\Freezed\Exception\PathNotAllowedException;

/**
 * File system access for the build.
 *
 * One rule applies to every file a build reads on behalf of a template: it
 * must lie below the project directory or below one of the configured
 * assetRoots. assertAllowedPath() enforces it; the ViewHelpers and the content
 * sources call it before touching a file, so a stray ".." in a template or a
 * path that arrived through the data cannot pull files from elsewhere on the
 * machine into public/.
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
        $this->targetDirectory = ConfigService::getInstance()
                ->getValue('[projectRoot]') . '/' . ConfigService::getInstance()
                ->getValue('[publicPath]');
    }

    public function clearTargetDirectory(): void
    {
        $this->clearDirectoryContent($this->targetDirectory, true);
    }

    public function clearDirectoryContent(
        string $path,
        bool $recursive = true
    ): void {

        /* check if path is inside this projects: */

        $path = realpath($path);
        if (!$path) {
            throw new \Exception('Path does not exist');
        }

        $projectRoot = ConfigService::getInstance()->getValue('[projectRoot]');
        if (!$projectRoot || !str_starts_with($path, $projectRoot)) {
            throw new \Exception('Path is not inside project root');
        }

        foreach (self::directoryItems($path) as $file) {
            // A .git directory below public/ belongs to a deployment setup, not
            // to the build output. Deleting it would be unrecoverable, so it is
            // the one entry a build leaves alone.
            if (basename($file) === '.git') {
                continue;
            }

            if (is_file($file)) {
                unlink($file);
                LogService::getInstance()->add('Delete file: ' . $file,
                    LogService::TYPES['info']);
            }
            if ($recursive && is_dir($file)) {
                $this->clearDirectoryContent($file, true);
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

    public function writeFile(string $path, string $content): void
    {
        $path = $this->targetDirectory . '/' . $path;
        // Ensure the target subdirectory exists (e.g. public/cases/ for a
        // content type with a non-empty targetDirectory).
        $this->createDirectoryIfNotExist(dirname($path));
        file_put_contents($path, $content);
        LogService::getInstance()->add('Write file: ' . $path,
            LogService::TYPES['info']);
    }

    public function copyResource(Resource $resource): void
    {
        $targetPath = self::virtualRealpath(implode(DIRECTORY_SEPARATOR, [
            $this->targetDirectory,
            ConfigService::getInstance()
                ->getValue('[assetsDirectory]'),
            $resource->getTargetPath()
        ]));

        $this->createDirectoryIfNotExist(dirname($targetPath));

        copy($resource->getSourcePath(), $targetPath);
        LogService::getInstance()->add('Copy resource: '
            . $resource->getSourcePath() . ' to ' . $targetPath,
            LogService::TYPES['info']);
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
     */
    public function copyDirectoryItems(string $source, string $target): void
    {
        $source = self::virtualRealpath($source);
        $target = self::virtualRealpath($target);

        $this->createDirectoryIfNotExist($target);

        foreach (self::directoryItems($source) as $file) {
            if (in_array(basename($file), self::IGNORED_FILE_NAMES, true)) {
                continue;
            }

            if (is_file($file)) {
                $targetFile = $target . '/' . basename($file);
                copy($file, $targetFile);
                LogService::getInstance()->add('Copy file: ' . $file . ' to '
                    . $targetFile, LogService::TYPES['info']);
            }
            if (is_dir($file)) {
                $targetDirectory = $target . '/' . basename($file);
                $this->copyDirectoryItems($file, $targetDirectory);
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
        $configService = ConfigService::getInstance();
        $themesPath = $configService->getValue('[projectRoot]') . '/'
            . $configService->getValue('[themesPath]');
        $contentPath = $configService->getValue('[projectRoot]') . '/'
            . $configService->getValue('[contentPath]');

        // Paths arrive both as configured (projectRoot + contentPath) and as
        // real paths, so match against both spellings of each root.
        foreach ([$themesPath, $contentPath] as $root) {
            foreach (array_unique([$root, realpath($root) ?: $root]) as $candidate) {
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
     * Real path of the project root.
     */
    public static function getProjectRootRealPath(): string
    {
        $projectRoot = (string) ConfigService::getInstance()->getValue('[projectRoot]');

        return realpath($projectRoot) ?: rtrim($projectRoot, '/\\');
    }

    /**
     * True for "/etc/passwd", "C:\\data" and "\\\\server\\share".
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
     * Make sure a real path lies below the project directory, one of the
     * configured assetRoots, or one of the additional roots given, and throw
     * otherwise.
     *
     * @param string   $realPath    The resolved (realpath) file or directory.
     * @param string   $description What the path is, for the error message,
     *                              e.g. 'freezed:image src "…"'.
     * @param string[] $extraRoots  Further real paths that are acceptable, e.g.
     *                              the template root of the item being rendered.
     *
     * @throws PathNotAllowedException
     */
    public static function assertAllowedPath(string $realPath, string $description, array $extraRoots = []): void
    {
        $roots = array_merge(
            [self::getProjectRootRealPath()],
            array_values(AssetRootService::getInstance()->getRoots()),
            $extraRoots
        );

        foreach ($roots as $root) {
            if ($root !== '' && self::isInside($realPath, $root)) {
                return;
            }
        }

        throw new PathNotAllowedException(sprintf(
            '%s resolves to "%s", outside the project directory. Freezed only reads files below the project root and the configured assetRoots.',
            $description,
            $realPath
        ));
    }

}
