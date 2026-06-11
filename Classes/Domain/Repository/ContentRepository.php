<?php

namespace Neuedaten\Freezed\Domain\Repository;

use Neuedaten\Freezed\Services\ConfigService;

class ContentRepository {

    protected array $contentTypeRepositories = [];

    public function findAllContentTypeRepositories(): array
    {
        if (count($this->contentTypeRepositories) === 0) {
            $this->findContentTypes();
        }

        return $this->contentTypeRepositories;
    }

    private function findContentTypes(): void
    {
        $path = ConfigService::getInstance()->getValue('[projectRoot]') . '/content/';
        $directories = glob($path . '*', GLOB_ONLYDIR);

        foreach ($directories as $directory) {
            $typeSlug = basename($directory);
            $this->contentTypeRepositories[] = $this->createContentTypeRepository($typeSlug, $directory);
        }
    }

    private function createContentTypeRepository(string $typeSlug, string $directory): ContentTypeRepository
    {
        $repository = new ContentTypeRepository($typeSlug, $directory);

        return $repository;
    }
}
