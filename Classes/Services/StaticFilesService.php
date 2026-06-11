<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Domain\Repository\ThemeRepository;

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

        $staticPaths = [];

        $mainStaticPath = $this->getStaticPath();
        if ($mainStaticPath) {
            $staticPaths[] = $mainStaticPath;
        }

        $staticPaths = array_merge($staticPaths, $this->getStaticPathsFromThemes());

        // Absolute target so the copy works regardless of the current working directory.
        $configService = ConfigService::getInstance();
        $publicPath = $configService->getValue('[projectRoot]') . DIRECTORY_SEPARATOR
            . $configService->getValue('[publicPath]');

        foreach ($staticPaths as $staticPath) {
            $fileService->copyDirectoryItems($staticPath, $publicPath);
        }
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
