<?php

namespace Neuedaten\Freezed\Domain\Repository;


use Neuedaten\Freezed\Domain\Model\Resource;
use Neuedaten\Freezed\Services\AssetVersionService;
use Neuedaten\Freezed\Services\ConfigService;
use Neuedaten\Freezed\Services\FileService;
use Neuedaten\Freezed\Services\LogService;

class ResourceRepository
{

    protected static self|null $instance = null;

    protected array $resources = [];

    protected array $resourcesByPath = [];

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Register a resource for copying and compute its public URL.
     *
     * Resources are memoised by source path, so a file referenced from many
     * pages is hashed exactly once per build and always yields the same URL.
     */
    public function createModelFromPath(string $path): Resource
    {
        if ($resource = $this->getExistingResource($path)) {
            return $resource;
        }

        $resource = new Resource();

        $resource->setSourcePath($path);
        $resource->setType($this->extractFileExtension($path));

        $version = AssetVersionService::isEnabled()
            ? AssetVersionService::hash($path)
            : '';
        $resource->setIdentifier($version);

        // The file keeps its name on disk; only the URL carries the version.
        $targetPath = FileService::virtualRealpath(FileService::getPathWithoutThemeOrContentDirectory($path));

        $resource->setTargetPath($targetPath);

        $publicPath = FileService::virtualRealpath(ConfigService::getInstance()
                ->getValue('[assetsDirectory]')
            . $resource->getTargetPath());

        $resource->setPublicPath(AssetVersionService::applyToUrl($publicPath, $version));

        $this->resources[] = $resource;
        $this->resourcesByPath[$path] = $resource;
        return $resource;
    }

    /*
       looks for and existing file in the highest order and creates a new resource object
    */
    public function createModelFromPaths(array $paths): Resource|false
    {
        $existingPaths = [];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                $existingPaths[] = $path;
            }
        }

        if (count($existingPaths) === 0) {
            LogService::getInstance()->add('No existing file found in paths: ' . implode(', ', $paths), LogService::TYPES['warning']);
            return false;
        }

        return $this->createModelFromPath($existingPaths[count($existingPaths) -1]);
    }

    public function getExistingResource(string $path): Resource|false
    {
        if (isset($this->resourcesByPath[$path])) {
            return $this->resourcesByPath[$path];
        }

        return false;
    }

    private function extractFileExtension(string $path): string
    {
        return pathinfo($path, PATHINFO_EXTENSION);
    }

    public function findAll(): array
    {
        return $this->resources;
    }

}
