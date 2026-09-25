<?php

namespace Neuedaten\Freezed\Domain\Repository;

use Neuedaten\Freezed\Domain\Model\Theme;
use Neuedaten\Freezed\Services\FileService;
use Neuedaten\Freezed\Services\ProjectPathsService;

class ThemeRepository
{

    protected static self|null $instance = null;

    protected array $themes = [];

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function findAll(): array
    {
        if (count($this->themes) > 0) {
            return $this->themes;
        }

        $path = ProjectPathsService::getInstance()->getLexicalPath('themesPath');

        $directories = glob($path . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($directories as $itemDirectory) {
            $real = realpath($itemDirectory);
            if ($real === false) {
                continue;
            }

            // A symlinked theme folder must point inside the project: its
            // templates are read and its assets are published.
            FileService::assertAllowedPath($real, 'Theme folder "' . $itemDirectory . '"');

            $this->themes[] = $this->createModelFromPath($real);
        }

        return $this->themes;
    }

    private function createModelFromPath(string $path): Theme
    {
        $model = new Theme();
        $model->setPath($path);
        return $model;
    }

}
