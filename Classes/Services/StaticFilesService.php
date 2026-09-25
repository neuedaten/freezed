<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Domain\Repository\ThemeRepository;
use Neuedaten\Freezed\Exception\PathNotAllowedException;

/**
 * Copies the contents of the project's static/ directory and of every theme's
 * static/ directory into public/, keeping the relative paths.
 *
 * The project directory is copied first, the themes follow in theme order, and
 * every copy overwrites the previous one — so a theme's static file wins over
 * the project's file of the same name. resolvePath() walks the same list in the
 * same order, so the URL a template gets always describes the file that was
 * actually published.
 */
class StaticFilesService
{

    protected static self|null $instance = null;

    protected array $files = [];

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function copyStaticFiles() {
        $fileService = new FileService();

        // Absolute target so the copy works regardless of the current working directory.
        $publicPath = ProjectPathsService::getInstance()->getRealPath('publicPath');

        foreach ($this->staticPaths() as $staticPath) {
            $fileService->copyDirectoryItems($staticPath, $publicPath);
        }
    }

    /**
     * Absolute path of a static file, resolved the way copyStaticFiles()
     * publishes it: the last directory that holds the file wins.
     *
     * Static files are copied by copyStaticFiles(), never registered as a
     * Resource — going through ResourceRepository would copy them a second time
     * and to the wrong place, because the target path would keep the theme and
     * static/ segments.
     */
    public function resolvePath(string $relativePath): string|false
    {
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '') {
            return false;
        }

        $resolved = false;

        foreach ($this->staticPaths() as $staticPath) {
            $candidate = realpath($staticPath . '/' . $relativePath);
            if ($candidate === false || !is_file($candidate)) {
                continue;
            }

            // The URL is built from the relative path, so the file must sit
            // below the static directory it was found in -- and, like every
            // file a build reads, inside the project.
            if (!FileService::isInside($candidate, realpath($staticPath) ?: $staticPath)) {
                throw new PathNotAllowedException(sprintf(
                    'Static file "%s" resolves to "%s", outside the static directory "%s".',
                    $relativePath,
                    $candidate,
                    $staticPath
                ));
            }

            $resolved = $candidate;
        }

        return $resolved;
    }

    /**
     * All static source directories, in publishing order: the project first,
     * then the themes.
     *
     * @return string[]
     */
    private function staticPaths(): array
    {
        $staticPaths = [];

        $mainStaticPath = $this->getStaticPath();
        if ($mainStaticPath) {
            $staticPaths[] = $mainStaticPath;
        }

        return array_merge($staticPaths, $this->getStaticPathsFromThemes());
    }

    private function getStaticPath(): string|false
    {
        return realpath(ProjectPathsService::getInstance()->getLexicalPath('staticPath'));
    }

    private function getStaticPathsFromThemes(): array
    {
        $themes = ThemeRepository::getInstance()->findAll();
        $staticPaths = [];
        foreach ($themes as $theme) {
            $path = $theme->getStaticPath();
            if (!$path) {
                continue;
            }

            $staticPaths[] = $theme->getStaticPath();
        }
        return $staticPaths;
    }
}
