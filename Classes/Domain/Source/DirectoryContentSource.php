<?php

namespace Neuedaten\Freezed\Domain\Source;

use Neuedaten\Freezed\Services\ConfigService;

/**
 * The default content source: every sub-folder of content/<type>/ is an item.
 *
 * The folder name is the slug, the folder's variables.php supplies the
 * variables, and the folder itself is the template root of the item (its
 * index.html is rendered, assets are resolved relative to it).
 *
 * Items carry an internal "directory" key with the folder's path below
 * content/. It is what makes the folder the template root; other sources
 * leave it out.
 */
class DirectoryContentSource implements ContentSourceInterface
{
    public function findAll(string $typeSlug, array $contentTypeConfig): iterable
    {
        $typeDirectory = self::getTypeDirectory($typeSlug);

        $directories = glob($typeDirectory . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($directories as $itemDirectory) {
            // The folder name as it appears in content/ is the slug, also for
            // a symlinked folder; the repository resolves the real path.
            yield [
                'slug' => basename($itemDirectory),
                'variables' => $this->readVariablesFile($itemDirectory),
                'directory' => $itemDirectory,
            ];
        }
    }

    /**
     * Folder changes are picked up by the file watcher itself, so there is no
     * separate version.
     */
    public function getVersion(string $typeSlug, array $contentTypeConfig): ?string
    {
        return null;
    }

    /**
     * Absolute path of content/<type>/.
     */
    public static function getTypeDirectory(string $typeSlug): string
    {
        $configService = ConfigService::getInstance();

        return $configService->getValue('[projectRoot]') . '/'
            . trim((string) $configService->getValue('[contentPath]'), '/') . '/'
            . $typeSlug;
    }

    private function readVariablesFile(string $itemDirectory): array
    {
        $variablesFile = $itemDirectory . '/variables.php';
        if (!file_exists($variablesFile)) {
            return [];
        }

        $variables = include $variablesFile;

        return is_array($variables) ? $variables : [];
    }
}
