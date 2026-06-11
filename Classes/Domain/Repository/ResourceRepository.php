<?php

namespace Neuedaten\Freezed\Domain\Repository;


use Neuedaten\Freezed\Domain\Model\Resource;
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

    public function createModelFromPath(string $path): Resource
    {
        if ($resource = $this->getExistingResource($path)) {
            return $resource;
        }

        $resource = new Resource();

        $uniqueIdentifier = $this->generateUniqueIdentifier($path);

        $resource->setSourcePath($path);
        $resource->setType($this->extractFileExtension($path));
        $resource->setConvertedName($this->generateUniqueFilename($path,
            $uniqueIdentifier));

        $targetPath = FileService::virtualRealpath(FileService::getPathWithoutThemeOrContentDirectory($path));

        $resource->setTargetPath($targetPath);

        $resource->setPublicPath(FileService::virtualRealpath(ConfigService::getInstance()
                ->getValue('[assetsDirectory]')
            . $resource->getTargetPath()));

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

    private function generateUniqueFilename(
        string $path,
        $uniqueIdentifier
    ): string {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return $uniqueIdentifier . '.' . $extension;
    }

    private function generateUniqueIdentifier($path): string
    {
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $filename = $this->convertFilename($filename);
        $uniqueId = uniqid();
        return $filename . '-' . $uniqueId;
    }

    private function convertFilename($filename): string
    {
        return preg_replace('/\s+/', '-',
            preg_replace('/[^a-z0-9\s]/', '', strtolower($filename)));
    }

    public function findAll(): array
    {
        return $this->resources;
    }

}
