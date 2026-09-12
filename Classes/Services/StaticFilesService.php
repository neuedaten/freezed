<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Domain\Repository\ThemeRepository;

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
        $configService = ConfigService::getInstance();
        $publicPath = $configService->getValue('[projectRoot]') . DIRECTORY_SEPARATOR
            . $configService->getValue('[publicPath]');

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
            if ($candidate !== false && is_file($candidate)) {
                $resolved = $candidate;
            }
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
        $path = ConfigService::getInstance()->getValue('[projectRoot]') . '/' . ConfigService::getInstance()->getValue('[staticPath]');

        return realpath ($path);
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
