<?php

namespace Neuedaten\Freezed\Domain\Source;

use Neuedaten\Freezed\Exception\ContentSourceException;

/**
 * A JSON file as content source, configured with its path relative to the
 * project root:
 *
 *     'source' => 'data/entries.json',
 *
 * This is the bridge for data produced outside PHP: a build hook
 * ('scripts' => ['start' => ['node export.js']]) writes the file, Freezed
 * reads it. The file holds either a list of items, or an object whose keys
 * are content type slugs, each with a list of items -- so one file can feed
 * several content types:
 *
 *     [ {"slug": "first", "variables": {"title": "First"}}, … ]
 *     { "entries": [ … ], "spots": [ … ] }
 *
 * The version for `freezed watch` is derived from the file's size and
 * modification time.
 */
class JsonContentSource implements ContentSourceInterface
{
    protected string $filePath;

    /** @var array<string, mixed>|null */
    protected ?array $data = null;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function findAll(string $typeSlug, array $contentTypeConfig): iterable
    {
        $data = $this->load();

        if (array_key_exists($typeSlug, $data) && is_array($data[$typeSlug]) && !array_is_list($data)) {
            return $data[$typeSlug];
        }

        if (array_is_list($data)) {
            return $data;
        }

        throw new ContentSourceException(sprintf(
            'Content source "%s" has no items for content type "%s": expected a list of items or an object with an "%s" key.',
            $this->filePath,
            $typeSlug,
            $typeSlug
        ));
    }

    public function getVersion(string $typeSlug, array $contentTypeConfig): ?string
    {
        clearstatcache(true, $this->filePath);

        if (!is_file($this->filePath)) {
            return null;
        }

        return filemtime($this->filePath) . ':' . filesize($this->filePath);
    }

    private function load(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        if (!is_file($this->filePath)) {
            throw new ContentSourceException('Content source file not found: ' . $this->filePath);
        }

        try {
            $data = json_decode((string) file_get_contents($this->filePath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ContentSourceException(sprintf(
                'Content source "%s" is not valid JSON: %s',
                $this->filePath,
                $exception->getMessage()
            ));
        }

        if (!is_array($data)) {
            throw new ContentSourceException(sprintf(
                'Content source "%s" must contain a JSON array or object, got %s.',
                $this->filePath,
                get_debug_type($data)
            ));
        }

        return $this->data = $data;
    }
}
