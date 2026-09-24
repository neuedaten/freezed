<?php

namespace Neuedaten\Freezed\Domain\Source;

use Neuedaten\Freezed\Exception\ContentSourceException;

/**
 * A PHP script as content source, configured with its path relative to the
 * project root:
 *
 *     'source' => 'data/entries.php',
 *
 * The script works like a variables.php: it is included and returns the list
 * of items (see ContentSourceInterface for the item format). The variables
 * $typeSlug and $contentTypeConfig are in scope, so one script can serve
 * several content types.
 *
 * Alternatively the script may return an object implementing
 * ContentSourceInterface -- e.g. an anonymous class -- which is then used in
 * its place. That is the way to get a getVersion() for `freezed watch`
 * without a Composer-autoloaded class; a script that returns a plain array
 * has no version, so watch only notices changes to the script file itself.
 */
class ScriptContentSource implements ContentSourceInterface
{
    protected string $scriptPath;

    protected ?ContentSourceInterface $delegate = null;

    /** True once the script has been seen to return a plain array. */
    protected bool $returnsArray = false;

    public function __construct(string $scriptPath)
    {
        $this->scriptPath = $scriptPath;
    }

    public function findAll(string $typeSlug, array $contentTypeConfig): iterable
    {
        $result = $this->run($typeSlug, $contentTypeConfig);

        if ($result instanceof ContentSourceInterface) {
            return $result->findAll($typeSlug, $contentTypeConfig);
        }

        if (!is_array($result)) {
            throw new ContentSourceException(sprintf(
                'Content source script "%s" must return an array of items or a %s, got %s.',
                $this->scriptPath,
                ContentSourceInterface::class,
                get_debug_type($result)
            ));
        }

        return $result;
    }

    public function getVersion(string $typeSlug, array $contentTypeConfig): ?string
    {
        // Only a returned source object can report a version. Including the
        // script on every poll just to hash a plain array would run the full
        // data load every few hundred milliseconds, so a plain script is
        // asked once and then left alone.
        if ($this->returnsArray) {
            return null;
        }

        $result = $this->run($typeSlug, $contentTypeConfig);

        return $result instanceof ContentSourceInterface
            ? $result->getVersion($typeSlug, $contentTypeConfig)
            : null;
    }

    /**
     * Include the script (once it has returned a source object, that object is
     * reused and the script is not included again).
     */
    private function run(string $typeSlug, array $contentTypeConfig): mixed
    {
        if ($this->delegate !== null) {
            return $this->delegate;
        }

        $result = (static function (string $__file, string $typeSlug, array $contentTypeConfig): mixed {
            return include $__file;
        })($this->scriptPath, $typeSlug, $contentTypeConfig);

        if ($result instanceof ContentSourceInterface) {
            $this->delegate = $result;
        } else {
            $this->returnsArray = true;
        }

        return $result;
    }
}
