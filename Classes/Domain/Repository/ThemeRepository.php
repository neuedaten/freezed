<?php

namespace Neuedaten\Freezed\Domain\Repository;

use Neuedaten\Freezed\Domain\Model\Theme;
use Neuedaten\Freezed\Services\ConfigService;

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

        $path = ConfigService::getInstance()->getValue('[projectRoot]')
            . DIRECTORY_SEPARATOR
            . ConfigService::getInstance()->getValue('[themesPath]');

        $directories = glob($path . '/*', GLOB_ONLYDIR);
        foreach ($directories as $itemDirectory) {
            $itemDirectory = realpath($itemDirectory);
            $this->themes[] = $this->createModelFromPath($itemDirectory);
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
