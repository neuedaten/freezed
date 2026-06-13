<?php

namespace Neuedaten\Freezed\ViewHelpers;

use Neuedaten\Freezed\Services\ConfigService;
use Neuedaten\Freezed\Services\ImageService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Processes an image (resize, convert, re-encode) and returns the public path
 * of the generated file, e.g. for an <img src="…">. A scaled-down version of
 * TYPO3's f:image.
 *
 *     <img src="{freezed:image(src: 'assets/images/hero.jpg', context: 'theme', width: 800, fileType: 'webp', quality: 80)}" alt="">
 *
 * Arguments:
 *   src             Path to the source image (resolved like freezed:resource).
 *   context         'theme' to resolve from theme template roots; otherwise the content template root.
 *   width / height  Target size in px, or 'auto' (keeps aspect ratio).
 *   fileType        Output format: jpg, jpeg, png, webp, gif. Defaults to the source type.
 *   quality         Encoding quality for lossy formats (default from config).
 *   scaleUp         Allow enlarging beyond the original size (default false).
 */
class ImageViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('src', 'string', 'Path to the source image', true);
        $this->registerArgument('context', 'string', 'Resolution context ("theme" or default)', false);
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
     * ResourceViewHelper resolves resources.
     */
    private function resolveSourcePath(string $src, ?string $context): ?string
    {
        $templateRootPaths = $this->renderingContext->getTemplatePaths()->getTemplateRootPaths();
        $configService = ConfigService::getInstance();

        if ($context === 'theme') {
            $themesPath = $configService->getValue('[projectRoot]') . '/' . $configService->getValue('[themesPath]');

            // Prefer the theme highest in the override order (last match wins).
            $resolved = null;
            foreach ($templateRootPaths as $templateRootPath) {
                if (str_starts_with($templateRootPath, $themesPath)) {
                    $themePath = $this->getThemePath($themesPath, $templateRootPath);
                    $candidate = realpath($themesPath . '/' . $themePath . '/' . $src);
                    if ($candidate !== false) {
                        $resolved = $candidate;
                    }
                }
            }

            return $resolved;
        }

        $templateRootPath = $templateRootPaths[count($templateRootPaths) - 1];
        $candidate = realpath($templateRootPath . '/' . $src);

        return $candidate !== false ? $candidate : null;
    }

    private function getThemePath(string $themesPath, string $themeTemplatePath): string
    {
        $themeTemplatePathWithoutThemesPath = str_replace($themesPath, '', $themeTemplatePath);

        return explode('/', $themeTemplatePathWithoutThemesPath)[1];
    }
}
