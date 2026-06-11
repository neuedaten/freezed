<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Domain\Repository\ContentRepository;
use Neuedaten\Freezed\Domain\Repository\ResourceRepository;

class CompileService
{

    protected static self|null $instance = null;

    protected array $contentRepositories;

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function compile(): bool
    {
        $contentRepository = new ContentRepository();
        $this->contentRepositories
            = $contentRepository->findAllContentTypeRepositories();

        $renderService = RenderService::getInstance();

        $fileService = new FileService();
        $fileService->clearTargetDirectory();

        StaticFilesService::getInstance()->copyStaticFiles();

        $resourceRepository = ResourceRepository::getInstance();

        /* @var $contentRepository \Neuedaten\Freezed\Domain\Repository\ContentTypeRepository */
        foreach ($this->contentRepositories as $contentRepository) {
            $models = $contentRepository->findAll();
            /* @var $model \Neuedaten\Freezed\Domain\Model\ContentType */
            foreach ($models as $model) {
                $renderedContent = $renderService->renderContent($model);
                $model->setContent($renderedContent);

                $fileService->writeFile($model->getTargetDirectoryName() . '/'
                    . $model->getTargetFileNameWithExtension(), $renderedContent);
            }
        }

        $resources = $resourceRepository->findAll();

        foreach ($resources as $resource) {
            $fileService->copyResource($resource);
        }

        return true;
    }
}
