<?php

namespace Neuedaten\Freezed\ViewHelpers;

use Neuedaten\Freezed\Domain\Repository\ResourceRepository;
use Neuedaten\Freezed\Domain\Repository\ThemeRepository;
use Neuedaten\Freezed\Exception\PathNotAllowedException;
use Neuedaten\Freezed\Services\AssetRootService;
use Neuedaten\Freezed\Services\AssetVersionService;
use Neuedaten\Freezed\Services\FileService;
use Neuedaten\Freezed\Services\LogService;
use Neuedaten\Freezed\Services\StaticFilesService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Returns the public URL of an asset and schedules it for copying into public/.
 *
 *     <link rel="stylesheet" href="{freezed:resource(path: 'assets/css/main.css', context: 'theme')}">
 *
 * Arguments:
 *   path          Path to the file, relative to the context's root.
 *   context       'theme'  resolve from the theme roots (a later theme wins),
 *                 'static' resolve from the project's and the themes' static/ directories,
 *                 the name of an assetRoots entry to resolve from that folder,
 *                 otherwise resolve from the content template root.
 *   jsModuleName  Name of the JavaScript module of this resource.
 *
 * Unless assetVersioning is switched off, the URL carries a short content hash
 * (main.css?v=a1b2c3d4) so browsers pick up a changed file. Files from static/
 * are only versioned when assetVersioningStatic is enabled.
 *
 * Except for 'static', where the path doubles as the URL, path is always
 * relative; an absolute path is an error, and so is a path that resolves to a
 * place outside the project directory, its content, theme and static
 * directories and the assetRoots.
 */
class ResourceViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('path', 'string', 'The path to the resource', true);
        $this->registerArgument('context', 'string', 'Context of the resource: "theme", "static", an assetRoots name, or empty for the content template root', false);
        $this->registerArgument('jsModuleName', 'string', 'Name of the js module of this resource', false);
    }

    public function render(): string
    {
        $path = (string) $this->arguments['path'];
        $context = (string) ($this->arguments['context'] ?? '');

        // Fluid 5: read the template paths straight from the rendering context
        // (the ViewHelperVariableContainer::getView() chain is no public API).
        // The last root is the item's own folder.
        $templateRootPaths = $this->renderingContext->getTemplatePaths()->getTemplateRootPaths();

        $description = 'freezed:resource path "' . $path . '"';

        if ($context !== 'static' && FileService::isAbsolutePath($path)) {
            throw new PathNotAllowedException(
                $description . ' is absolute. Paths are relative to the context\'s root; '
                . 'add a folder to assetRoots and name it as context to read files from elsewhere in the project.'
            );
        }

        switch ($context) {
            case 'static':
                // Static files are published by StaticFilesService, so this only
                // builds the URL. Registering them as a Resource would copy them
                // a second time, to a path that does not match the URL.
                return $this->renderStatic($path);
            case 'theme':
                // Themes in stacking order (the same order RenderService
                // registers their template roots), so a later theme wins.
                $resourcePaths = [];
                foreach (ThemeRepository::getInstance()->findAll() as $theme) {
                    $resourcePaths[] = $theme->getPath() . '/' . $path;
                }

                $fullPath = $this->pickExistingPath($resourcePaths);
                if ($fullPath === null) {
                    LogService::getInstance()->add('No existing file found in paths: ' . implode(', ', $resourcePaths), LogService::TYPES['warning']);
                    return '';
                }

                FileService::assertAllowedPath($fullPath, $description);
                break;
            case '':
                $templateRootPath = $templateRootPaths[count($templateRootPaths) - 1];
                $fullPath = realpath($templateRootPath . '/' . $path);

                if ($fullPath === false) {
                    LogService::getInstance()->add('Resource not found: '
                        . $templateRootPath . '/' . $path,
                        LogService::TYPES['warning']);
                    return '';
                }

                FileService::assertAllowedPath($fullPath, $description);
                break;
            default:
                // Checked against the root by the service itself.
                $fullPath = AssetRootService::getInstance()->resolveFile($context, $path);

                if ($fullPath === null) {
                    LogService::getInstance()->add('Resource not found in assetRoots "' . $context . '": ' . $path,
                        LogService::TYPES['warning']);
                    return '';
                }
                break;
        }

        if (FileService::svgContainsScript($fullPath)) {
            LogService::getInstance()->warning(
                'Resource not published: ' . $fullPath . ' is an SVG with script, event handlers or embedded HTML.'
            );
            return '';
        }

        $resource = ResourceRepository::getInstance()->createModelFromPath($fullPath);

        if ($this->arguments['jsModuleName']) {
            $resource->setJsModuleName($this->arguments['jsModuleName']);
        }

        return $resource->getPublicPath();
    }

    /**
     * The last existing file of the candidates (the theme highest in the
     * override order wins), as a real path.
     */
    private function pickExistingPath(array $paths): ?string
    {
        $resolved = null;

        foreach ($paths as $path) {
            $candidate = realpath($path);
            if ($candidate !== false && is_file($candidate)) {
                $resolved = $candidate;
            }
        }

        return $resolved;
    }

    /**
     * URL of a file from static/. Those directories are copied into the public
     * root as-is, so the URL is the relative path with a leading slash and
     * assetsDirectory is not involved.
     */
    private function renderStatic(string $path): string
    {
        $sourcePath = StaticFilesService::getInstance()->resolvePath($path);

        if ($sourcePath === false) {
            LogService::getInstance()->add('Static file not found: ' . $path,
                LogService::TYPES['warning']);
            return '';
        }

        $url = '/' . ltrim($path, '/');

        if (!AssetVersionService::isEnabledForStatic()) {
            return $url;
        }

        return AssetVersionService::applyToUrlForFile($url, $sourcePath);
    }

}
