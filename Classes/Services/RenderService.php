<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Components\ComponentCollection;
use Neuedaten\Freezed\Domain\Model\ContentType;
use Neuedaten\Freezed\Domain\Repository\ThemeRepository;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;
use TYPO3Fluid\Fluid\View\TemplatePaths;
use TYPO3Fluid\Fluid\View\TemplateView;

class RenderService
{

    protected static self|null $instance = null;

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function renderContent(ContentType $contentType): string
    {
        $paths = new TemplatePaths();

        $templatesFromThemes = $this->getTemplatePathsFromThemes();

        $paths->setTemplateRootPaths(array_merge($templatesFromThemes['templateRootPaths'], [$contentType->getDirectoryPath()]));
        $paths->setLayoutRootPaths($templatesFromThemes['layoutRootPaths']);
        $paths->setPartialRootPaths($templatesFromThemes['partialRootPaths']);

        // Expose CLI build options (e.g. --enviroment:development) to templates
        // via the "build" variable, e.g. {build.enviroment}.
        $variables = $contentType->getVariables();
        $variables['build'] = ConfigService::getInstance()->getValue('[buildConfig]') ?? [];

        $context = new RenderingContext();
        $context->setTemplatePaths($paths);
        $context->setVariableProvider(new StandardVariableProvider($variables));
        $context->setControllerAction('index');

        $resolver = $context->getViewHelperResolver();
        $resolver->addNamespace('freezed', 'Neuedaten\Freezed\\ViewHelpers');

        // Register Fluid components from the themes' component folders under the
        // "component" namespace, e.g. <component:card>. AbstractComponentCollection
        // implements ViewHelperResolverDelegateInterface, so it can be passed to
        // addNamespace() directly — same mechanism as the ViewHelpers above.
        $componentRootPaths = $templatesFromThemes['componentRootPaths'];
        if ($componentRootPaths !== []) {
            $resolver->addNamespace('component', new ComponentCollection($componentRootPaths));
        }

//        $templateParser = new TemplateParser();
//        $templateParser->setRenderingContext($context);

        $view = new TemplateView($context);

        return $view->render();
    }

    private function getTemplatePathsFromThemes(): array
    {
        $themes = ThemeRepository::getInstance()->findAll();

        $templates = [
            'templateRootPaths' => [],
            'layoutRootPaths' => [],
            'partialRootPaths' => [],
            'componentRootPaths' => []
        ];

        /* @var $theme \Neuedaten\Freezed\Domain\Model\Theme */
        foreach ($themes as $theme) {

            $templateRootPath = $theme->getTemplateRootPath();
            if ($templateRootPath) {
                $templates['templateRootPaths'][] = $templateRootPath;
            }

            $layoutRootPath = $theme->getLayoutRootPath();
            if ($layoutRootPath) {
                $templates['layoutRootPaths'][] = $layoutRootPath;
            }

            $partialRootPath = $theme->getPartialRootPath();
            if ($partialRootPath) {
                $templates['partialRootPaths'][] = $partialRootPath;
            }

            $componentRootPath = $theme->getComponentRootPath();
            if ($componentRootPath) {
                $templates['componentRootPaths'][] = $componentRootPath;
            }
        }

        return $templates;
    }

}
