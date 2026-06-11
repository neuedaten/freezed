<?php

namespace Neuedaten\Freezed\ViewHelpers;

use Neuedaten\Freezed\Domain\Repository\ResourceRepository;
use Neuedaten\Freezed\Services\ConfigService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class ResourceViewHelper extends AbstractViewHelper
{
    public function initializeArguments(): void
    {
        $this->registerArgument('path', 'string', 'The path to the resource', true);
        $this->registerArgument('context', 'string', 'Context of the resource', false);
        $this->registerArgument('jsModuleName', 'string', 'Name of the js module of this resource', false);
    }

    public function render(): string
    {
        $path = $this->arguments['path'];
        $context = $this->arguments['context'];

        $templateRootPaths = $this->viewHelperVariableContainer->getView()->getRenderingContext()->getTemplatePaths()->getTemplateRootPaths();

        $configService = ConfigService::getInstance();

        switch ($context) {
            case 'theme':
                $themesPath = $configService->getValue('[projectRoot]') . '/' . $configService->getValue('[themesPath]');

                $resourcePaths = [];

                foreach ($templateRootPaths as $templateRootPath) {
                    if (str_starts_with($templateRootPath, $themesPath)) {
                        $themePath = self::getThemePath($themesPath, $templateRootPath);
                        $resourcePaths[] = $themesPath . '/' . $themePath . '/' . $path;
                    }
                }

                $resourceRepository = ResourceRepository::getInstance();
                $resource = $resourceRepository->createModelFromPaths($resourcePaths);
                break;
            default:
                $templateRootPath = $templateRootPaths[count($templateRootPaths) - 1];
                $fullPath = realpath($templateRootPath . '/' . $path);
                $resourceRepository = ResourceRepository::getInstance();
                $resource = $resourceRepository->createModelFromPath($fullPath);
                break;
        }

        if (!$resource) {
            return '';
        }

        if ($this->arguments['jsModuleName']) {
            $resource->setJsModuleName($this->arguments['jsModuleName']);
        }

        return $resource->getPublicPath();
    }

    private function getThemePath($themesPath, $themeTemplatePath): string
    {
        $themeTemplatePathWithoutThemesPath = str_replace($themesPath, '',
            $themeTemplatePath);
        return explode('/', $themeTemplatePathWithoutThemesPath)[1];
    }
}
