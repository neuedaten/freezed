<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Components\ComponentCollection;
use Neuedaten\Freezed\Domain\Model\ContentType;
use Neuedaten\Freezed\Domain\Repository\ThemeRepository;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContext;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;
use TYPO3Fluid\Fluid\Core\ViewHelper\ViewHelperResolverDelegateInterface;
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
        $context->setControllerAction($contentType->getTemplate());

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

    /**
     * Render one Fluid template file with the given variables, outside the
     * content pipeline -- for a package that brings templates of its own,
     * such as a web UI. The template is addressed by its path; layouts,
     * partials and components come from $paths (later entries win, as with
     * themes):
     *
     *     RenderService::getInstance()->renderFile(
     *         '/path/to/templates/Records/Index.html',
     *         ['items' => $items],
     *         [
     *             'layoutRootPaths' => [$theme . '/layouts/'],
     *             'partialRootPaths' => [$theme . '/partials/'],
     *             'componentRootPaths' => [$theme . '/components/'],
     *         ],
     *         ['desk' => 'Neuedaten\\FreezedDesk\\ViewHelpers']
     *     );
     *
     * The "freezed" namespace, the "component" namespace (for the given
     * component roots) and the "build" variable are available as in content
     * templates. A namespace value is a ViewHelper class prefix or a
     * ViewHelperResolverDelegateInterface (a component collection).
     *
     * @param array<string, mixed>                                            $variables
     * @param array<string, string[]>                                          $paths      layoutRootPaths, partialRootPaths, componentRootPaths, templateRootPaths
     * @param array<string, string|ViewHelperResolverDelegateInterface>        $namespaces prefix => namespace
     */
    public function renderFile(string $templateFile, array $variables = [], array $paths = [], array $namespaces = []): string
    {
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths($paths['templateRootPaths'] ?? [dirname($templateFile) . '/']);
        $templatePaths->setLayoutRootPaths($paths['layoutRootPaths'] ?? []);
        $templatePaths->setPartialRootPaths($paths['partialRootPaths'] ?? []);
        $templatePaths->setTemplatePathAndFilename($templateFile);

        $variables['build'] = $variables['build'] ?? (ConfigService::getInstance()->getValue('[buildConfig]') ?? []);

        $context = new RenderingContext();
        $context->setTemplatePaths($templatePaths);
        $context->setVariableProvider(new StandardVariableProvider($variables));

        $resolver = $context->getViewHelperResolver();
        $resolver->addNamespace('freezed', 'Neuedaten\\Freezed\\ViewHelpers');

        $componentRootPaths = array_values(array_filter($paths['componentRootPaths'] ?? [], 'is_dir'));
        if ($componentRootPaths !== []) {
            $resolver->addNamespace('component', new ComponentCollection($componentRootPaths));
        }

        foreach ($namespaces as $prefix => $namespace) {
            $resolver->addNamespace((string) $prefix, $namespace);
        }

        return (new TemplateView($context))->render();
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
