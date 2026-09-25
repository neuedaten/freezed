<?php

namespace Neuedaten\Freezed\Domain\Repository;

use Neuedaten\Freezed\Domain\Model\ContentType;
use Neuedaten\Freezed\Domain\Source\ContentSourceInterface;
use Neuedaten\Freezed\Exception\ContentSourceException;
use Neuedaten\Freezed\Services\ConfigService;
use Neuedaten\Freezed\Services\ContentSourceService;
use Neuedaten\Freezed\Services\FileService;

/**
 * The items of one content type as ContentType models.
 *
 * Where the items come from is decided by the type's "source" setting (see
 * ContentSourceService): by default the folders below content/<type>/, or a
 * class, script or JSON file. This repository turns whatever the source
 * delivers into models, so everything downstream -- the compile loop, the
 * sitemap, CONTENT: links, contentTypeCollection -- never needs to know.
 */
class ContentTypeRepository {

    protected string $typeSlug;
    protected string $directory;
    protected array $models = [];

    protected array $config;

    protected array $contentTypeConfig;

    protected ContentSourceInterface $source;

    public function __construct(string $typeSlug, string $directory) {
        $this->typeSlug = $typeSlug;
        $this->directory = realpath($directory) ?: rtrim($directory, '/\\');

        $contentTypeConfig = ConfigService::getInstance()->getValue('[contentTypes][' . $typeSlug .']');

        if ($contentTypeConfig === null) {
            throw new \Exception('No configuration found for content type ' . $typeSlug);
        } else {
            $this->contentTypeConfig = $contentTypeConfig;
        }

        $this->source = ContentSourceService::getInstance()->getSource($typeSlug, $this->contentTypeConfig);
    }

    public function getTypeSlug(): string
    {
        return $this->typeSlug;
    }

    public function getSource(): ContentSourceInterface
    {
        return $this->source;
    }

    /**
     * @return array<int, ContentType>
     * @throws ContentSourceException When the source delivers an unusable item.
     */
    public function findAll(): array
    {
        if (count($this->models) > 0) {
            return $this->models;
        }

        $slugs = [];
        $position = 0;

        foreach ($this->source->findAll($this->typeSlug, $this->contentTypeConfig) as $item) {
            $position++;
            $model = $this->createModelFromItem($item, $position);

            if (isset($slugs[$model->getTitle()])) {
                throw new ContentSourceException(sprintf(
                    'Content type "%s": the source delivered two items with the slug "%s".',
                    $this->typeSlug,
                    $model->getTitle()
                ));
            }

            $slugs[$model->getTitle()] = true;
            $this->models[] = $model;
        }

        return $this->models;
    }

    /**
     * @param mixed $item     One entry from the source, see ContentSourceInterface.
     * @param int   $position 1-based index of the item, for error messages.
     */
    private function createModelFromItem(mixed $item, int $position): ContentType
    {
        if (!is_array($item)) {
            throw new ContentSourceException(sprintf(
                'Content type "%s": item #%d from the source is not an array (got %s).',
                $this->typeSlug,
                $position,
                get_debug_type($item)
            ));
        }

        $slug = $this->normaliseSlug($item['slug'] ?? null, $position);
        $directory = $this->resolveItemDirectory($item['directory'] ?? null, $slug);

        $model = new ContentType($this->typeSlug, $slug, $directory);
        $model->setOwnDirectory($directory !== $this->directory);
        $model->setTargetDirectoryName($this->contentTypeConfig['targetDirectory']);
        $model->setTargetFileExtension($this->contentTypeConfig['targetFileExtension']);
        $model->setTargetFileName($slug);
        $model->setTemplate($this->normaliseTemplate($item['template'] ?? null, $slug));

        // Variable precedence, low to high:
        //   1. Site-wide variables (top-level "variables" in freezed.config.php),
        //      available to every content type.
        //   2. The content type's own "variables" (overrides the site-wide ones).
        //   3. The item's variables: its variables.php for folder items, the
        //      "variables" key for items from another source (overrides both).
        $globalVariables = ConfigService::getInstance()->getValue('[variables]') ?? [];
        $contentTypeVariables = $this->contentTypeConfig['variables'] ?? [];
        $itemVariables = $item['variables'] ?? [];

        if (!is_array($itemVariables)) {
            throw new ContentSourceException(sprintf(
                'Content type "%s": "variables" of item "%s" must be an array (got %s).',
                $this->typeSlug,
                $slug,
                get_debug_type($itemVariables)
            ));
        }

        $model->setVariables(array_merge($globalVariables, $contentTypeVariables, $itemVariables));

        return $model;
    }

    /**
     * A slug is used like a folder name: as output file name, in CONTENT:
     * references and as "folderName" in collections. It may contain "/" for
     * nested output paths but must not climb out of the target directory.
     */
    private function normaliseSlug(mixed $slug, int $position): string
    {
        if (!is_string($slug) && !is_int($slug)) {
            throw new ContentSourceException(sprintf(
                'Content type "%s": item #%d from the source has no "slug".',
                $this->typeSlug,
                $position
            ));
        }

        $slug = trim(str_replace('\\', '/', (string) $slug), '/');

        if ($slug === '' || in_array('..', explode('/', $slug), true) || str_contains($slug, '//')) {
            throw new ContentSourceException(sprintf(
                'Content type "%s": item #%d has an invalid slug "%s".',
                $this->typeSlug,
                $position,
                $slug
            ));
        }

        return $slug;
    }

    /**
     * Template name inside the item's template root, without extension.
     * Defaults to "index" (content/<type>/<item>/index.html for folder items,
     * content/<type>/index.html for items from another source).
     */
    private function normaliseTemplate(mixed $template, string $slug): string
    {
        if ($template === null || $template === '') {
            return ContentType::DEFAULT_TEMPLATE;
        }

        if (!is_string($template)
            || !preg_match('#^[A-Za-z0-9_][A-Za-z0-9_./-]*$#', $template)
            || in_array('..', explode('/', $template), true)
        ) {
            throw new ContentSourceException(sprintf(
                'Content type "%s": item "%s" has an invalid template name "%s".',
                $this->typeSlug,
                $slug,
                is_string($template) ? $template : get_debug_type($template)
            ));
        }

        return $template;
    }

    /**
     * The item's template root: the item folder for folder items (the source
     * passes it as "directory"), otherwise the content type's folder, where
     * the shared templates of a sourced type live.
     *
     * A "directory" must be a folder inside the content directory on paper,
     * and its real path must lie inside the project or an asset root -- a
     * symlinked folder in content/ may point within those, not elsewhere.
     */
    private function resolveItemDirectory(mixed $directory, string $slug): string
    {
        if ($directory === null || $directory === '') {
            return $this->directory;
        }

        $contentRoot = dirname($this->directory);
        $lexical = is_string($directory) ? FileService::virtualRealpath($directory) : '';
        $resolved = $lexical !== '' ? realpath($lexical) : false;

        if ($resolved === false || !is_dir($resolved) || !FileService::isInside($lexical, $contentRoot)) {
            throw new ContentSourceException(sprintf(
                'Content type "%s": the directory of item "%s" must be a folder inside the content directory (got %s).',
                $this->typeSlug,
                $slug,
                is_string($directory) ? $directory : get_debug_type($directory)
            ));
        }

        FileService::assertAllowedPath($resolved, 'Content folder of item "' . $this->typeSlug . '/' . $slug . '"');

        return $resolved;
    }
}
