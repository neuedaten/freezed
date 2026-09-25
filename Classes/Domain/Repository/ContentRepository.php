<?php

namespace Neuedaten\Freezed\Domain\Repository;

use Neuedaten\Freezed\Services\FileService;
use Neuedaten\Freezed\Services\ProjectPathsService;

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
        $path = ProjectPathsService::getInstance()->getLexicalPath('contentPath') . '/';
        $directories = glob($path . '*', GLOB_ONLYDIR) ?: [];

        foreach ($directories as $directory) {
            $typeSlug = basename($directory);

            // A symlinked content type folder must point inside the project.
            $real = realpath($directory);
            if ($real !== false) {
                FileService::assertAllowedPath($real, 'Content type folder "' . $directory . '"');
            }

            $this->contentTypeRepositories[] = $this->createContentTypeRepository($typeSlug, $directory);
        }
    }

    private function createContentTypeRepository(string $typeSlug, string $directory): ContentTypeRepository
    {
        $repository = new ContentTypeRepository($typeSlug, $directory);

        return $repository;
    }
}
