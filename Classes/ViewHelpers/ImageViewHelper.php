<?php

namespace Neuedaten\Freezed\ViewHelpers;

use Neuedaten\Freezed\Domain\Repository\ThemeRepository;
use Neuedaten\Freezed\Exception\PathNotAllowedException;
use Neuedaten\Freezed\Services\AssetRootService;
use Neuedaten\Freezed\Services\FileService;
use Neuedaten\Freezed\Services\ImageService;
use Neuedaten\Freezed\Services\StaticFilesService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Processes an image (resize, convert, re-encode) and returns the public path
 * of the generated file, e.g. for an <img src="…">. A scaled-down version of
 * TYPO3's f:image.
 *
 *     <img src="{freezed:image(src: 'assets/images/hero.jpg', context: 'theme', width: 800, fileType: 'webp', quality: 80)}" alt="">
 *
 * Arguments:
 *   src             Path to the source image, relative to the context's root.
 *   context         Where to resolve src: 'theme' (theme roots, a later theme
 *                   wins), 'static' (the static/ directories), the name of an
 *                   assetRoots entry, or empty for the content template root.
 *   width / height  Target size in px, or 'auto' (keeps aspect ratio).
 *   fileType        Output format: jpg, jpeg, png, webp, gif. Defaults to the source type.
 *   quality         Encoding quality for lossy formats (default from config).
 *   scaleUp         Allow enlarging beyond the original size (default false).
 *
 * src is always relative; an absolute path is an error, and so is a path that
 * resolves to a place outside the project directory, its content, theme and
 * static directories and the assetRoots.
 */
class ImageViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('src', 'string', 'Path to the source image', true);
        $this->registerArgument('context', 'string', 'Resolution context: "theme", "static", an assetRoots name, or empty for the content template root', false);
        $this->registerArgument('width', 'string', 'Target width in px, or "auto"', false, 'auto');
        $this->registerArgument('height', 'string', 'Target height in px, or "auto"', false, 'auto');
        $this->registerArgument('fileType', 'string', 'Output format: jpg, png, webp, gif', false);
        $this->registerArgument('quality', 'int', 'Encoding quality for lossy formats (jpeg, webp)', false);
        $this->registerArgument('scaleUp', 'bool', 'Allow enlarging beyond the original size', false, false);
    }

    public function render(): string
    {
        $sourcePath = $this->resolveSourcePath((string) $this->arguments['src'], $this->arguments['context'] ?? null);
        if ($sourcePath === null) {
            return '';
        }

        return ImageService::getInstance()->process(
            $sourcePath,
            $this->parseDimension($this->arguments['width']),
            $this->parseDimension($this->arguments['height']),
            $this->arguments['fileType'] ?? null,
            $this->arguments['quality'] !== null ? (int) $this->arguments['quality'] : null,
            (bool) $this->arguments['scaleUp']
        );
    }

    /**
     * Turn a dimension argument into an int, treating "auto"/empty as null.
     */
    private function parseDimension(mixed $value): ?int
    {
        if ($value === null || $value === '' || strtolower((string) $value) === 'auto') {
            return null;
        }

        return (int) $value;
    }

    /**
     * Resolve the absolute path of the source image, mirroring how
     * ResourceViewHelper resolves resources. Returns null for a missing file
     * (ImageService logs it); throws for a path that breaks the file rule.
     *
     * @throws PathNotAllowedException
     */
    private function resolveSourcePath(string $src, ?string $context): ?string
    {
        $context = (string) $context;
        $description = 'freezed:image src "' . $src . '"';

        if (FileService::isAbsolutePath($src)) {
            throw new PathNotAllowedException(
                $description . ' is absolute. Paths are relative to the context\'s root; '
                . 'add a folder to assetRoots and name it as context to read files from elsewhere in the project.'
            );
        }

        $templateRootPaths = $this->renderingContext->getTemplatePaths()->getTemplateRootPaths();

        switch ($context) {
            case 'theme':
                // Themes in stacking order: the last theme that has the file wins.
                $resolved = null;
                foreach (ThemeRepository::getInstance()->findAll() as $theme) {
                    $candidate = realpath($theme->getPath() . '/' . $src);
                    if ($candidate !== false && is_file($candidate)) {
                        $resolved = $candidate;
                    }
                }
                break;

            case 'static':
                $resolved = StaticFilesService::getInstance()->resolvePath($src) ?: null;
                break;

            case '':
                $templateRootPath = $templateRootPaths[count($templateRootPaths) - 1];
                $candidate = realpath($templateRootPath . '/' . $src);
                $resolved = $candidate !== false ? $candidate : null;
                break;

            default:
                // Checked against the root by the service itself.
                return AssetRootService::getInstance()->resolveFile($context, $src);
        }

        if ($resolved !== null) {
            FileService::assertAllowedPath($resolved, $description);
        }

        return $resolved;
    }
}
