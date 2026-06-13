<?php

namespace Neuedaten\Freezed\Domain\Repository;

use Neuedaten\Freezed\Domain\Model\ContentType;
use Neuedaten\Freezed\Services\ConfigService;
use Symfony\Component\PropertyAccess\PropertyAccess;


class ContentTypeRepository {

    protected string $typeSlug;
    protected string $directory;
    protected array $models = [];

    protected array $config;

    protected array $contentTypeConfig;

    public function __construct(string $typeSlug, string $directory) {
        $this->typeSlug = $typeSlug;
        $this->directory = $directory;

        $contentTypeConfig = ConfigService::getInstance()->getValue('[contentTypes][' . $typeSlug .']');

        if ($contentTypeConfig === null) {
            throw new \Exception('No configuration found for content type ' . $typeSlug);
        } else {
            $this->contentTypeConfig = $contentTypeConfig;
        }
    }

    public function findAll(): array
    {
        if (count($this->models) > 0) {
            return $this->models;
        }

        $directories = glob($this->directory . '/*', GLOB_ONLYDIR);
        foreach ($directories as $itemDirectory) {
            $itemDirectory = realpath($itemDirectory);
            $this->models[] = $this->createModelFromDirectory($itemDirectory);
        }

        return $this->models;
    }

    private function createModelFromDirectory(string $itemDirectory): ContentType
    {
        $title = basename($itemDirectory);
        $model = new ContentType($this->typeSlug, $title, $itemDirectory);
        $model->setTargetDirectoryName($this->contentTypeConfig['targetDirectory']);
        $model->setTargetFileExtension($this->contentTypeConfig['targetFileExtension']);
        $model->setTargetFileName(basename($itemDirectory));

        // Variable precedence, low to high:
        //   1. Site-wide variables (top-level "variables" in freezed.config.php),
        //      available to every content type.
        //   2. The content type's own "variables" (overrides the site-wide ones).
        //   3. The item's variables.php (overrides both).
        $globalVariables = ConfigService::getInstance()->getValue('[variables]') ?? [];
        $contentTypeVariables = $this->contentTypeConfig['variables'] ?? [];
        $itemVariables = $this->readVariablesFileFromItemDirectory($itemDirectory);
        $model->setVariables(array_merge($globalVariables, $contentTypeVariables, $itemVariables));

        return $model;
    }

    private function readVariablesFileFromItemDirectory(string $itemDirectory): array
    {
        $variablesFile = $itemDirectory . '/variables.php';
        if (!file_exists($variablesFile)) {
            return [];
        }

        return include $variablesFile;
    }

}
