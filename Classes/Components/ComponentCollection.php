<?php

namespace Neuedaten\Freezed\Components;

use TYPO3Fluid\Fluid\Core\Component\AbstractComponentCollection;
use TYPO3Fluid\Fluid\View\TemplatePaths;

/**
 * Resolves Fluid components from the themes' component folders
 * (default: templates/components/ per theme).
 *
 * Registered on the "component" namespace in RenderService, so theme authors
 * can call <component:name /> without writing any PHP themselves.
 *
 * Folder convention (Fluid default): each component lives in its own folder,
 * e.g. <component:card> resolves to components/Card/Card.html. This keeps
 * component-specific assets next to the template.
 */
final class ComponentCollection extends AbstractComponentCollection
{
    /**
     * @param string[] $rootPaths Component root paths, ordered by theme priority
     */
    public function __construct(private readonly array $rootPaths) {}

    public function getTemplatePaths(): TemplatePaths
    {
        $templatePaths = new TemplatePaths();
        $templatePaths->setTemplateRootPaths($this->rootPaths);
        return $templatePaths;
    }
}
