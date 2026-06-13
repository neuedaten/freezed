<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Domain\Model\ContentType;
use Neuedaten\Freezed\Domain\Repository\ContentRepository;
use Neuedaten\Freezed\Domain\Repository\ResourceRepository;
use Neuedaten\Freezed\Exception\TemplateRenderException;

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

    /**
     * Compile the whole site into the public directory.
     *
     * @return array{pages: int, files: int, resources: int, durationMs: float}
     *         Build statistics, used by the CLI to print a summary.
     *
     * @throws TemplateRenderException When a content template fails to render.
     */
    public function compile(): array
    {
        $startTime = microtime(true);

        $contentRepository = new ContentRepository();
        $this->contentRepositories
            = $contentRepository->findAllContentTypeRepositories();

        $renderService = RenderService::getInstance();

        $fileService = new FileService();
        $fileService->clearTargetDirectory();

        StaticFilesService::getInstance()->copyStaticFiles();

        $resourceRepository = ResourceRepository::getInstance();

        $pageCount = 0;
        $fileCount = 0;

        /* @var $contentRepository \Neuedaten\Freezed\Domain\Repository\ContentTypeRepository */
        foreach ($this->contentRepositories as $contentRepository) {
            $models = $contentRepository->findAll();
            /* @var $model \Neuedaten\Freezed\Domain\Model\ContentType */
            foreach ($models as $model) {
                $renderedContent = $this->renderModel($renderService, $model);
                $model->setContent($renderedContent);

                $fileService->writeFile($model->getTargetDirectoryName() . '/'
                    . $model->getTargetFileNameWithExtension(), $renderedContent);

                $pageCount++;
                $fileCount++;
            }
        }

        $resources = $resourceRepository->findAll();

        foreach ($resources as $resource) {
            $fileService->copyResource($resource);
        }

        return [
            'pages' => $pageCount,
            'files' => $fileCount,
            'resources' => count($resources),
            'durationMs' => (microtime(true) - $startTime) * 1000,
        ];
    }

    /**
     * Render a single content model, converting any rendering failure into a
     * concise TemplateRenderException that names the offending template.
     *
     * @throws TemplateRenderException
     */
    private function renderModel(RenderService $renderService, ContentType $model): string
    {
        try {
            return $renderService->renderContent($model);
        } catch (\Throwable $exception) {
            throw new TemplateRenderException(
                $this->describeTemplate($model),
                $this->rootCauseMessage($exception),
                $exception
            );
        }
    }

    /**
     * Build a short, project-relative identifier for the model's template,
     * e.g. "content/pages/home".
     */
    private function describeTemplate(ContentType $model): string
    {
        $projectRoot = ConfigService::getInstance()->getValue('[projectRoot]');
        $path = $model->getDirectoryPath();

        if ($projectRoot && str_starts_with($path, $projectRoot)) {
            $path = ltrim(substr($path, strlen($projectRoot)), '/\\');
        }

        return $path !== '' ? $path : $model->getTypeSlug() . '/' . $model->getTitle();
    }

    /**
     * Walk to the deepest exception so the reported message points at the real
     * cause (e.g. an undefined Fluid variable) rather than a wrapper.
     */
    private function rootCauseMessage(\Throwable $exception): string
    {
        $cause = $exception;
        while ($cause->getPrevious() !== null) {
            $cause = $cause->getPrevious();
        }

        return $cause->getMessage();
    }
}
