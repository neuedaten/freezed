<?php

namespace Neuedaten\Freezed\ViewHelpers;

use Neuedaten\Freezed\Domain\Model\ContentType;
use Neuedaten\Freezed\Services\ContentUrlService;
use TYPO3Fluid\Fluid\Core\Variables\ScopedVariableProvider;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Collects all items of a given content type and exposes them as an array to
 * the child template, e.g. for teaser lists or menus.
 *
 * Each item holds every key from the item's variables.php, plus two derived
 * keys: "folderName" (the item directory name) and "url" (the public path the
 * item is built to, index.<ext> collapsing to the directory URL). The "as"
 * variable is only available inside the tag. "limit" caps the number of items
 * after sorting (default 100, 0 = no limit).
 *
 *     <freezed:contentTypeCollection contentType="cases" orderBy="title" orderDirection="DESC" limit="5" as="items">
 *         <f:for each="{items}" as="item">
 *             <a href="{item.url}">{item.title}</a>
 *         </f:for>
 *     </freezed:contentTypeCollection>
 */
class ContentTypeCollectionViewHelper extends AbstractViewHelper
{
    /**
     * Children may contain markup, so the rendered output must not be escaped.
     *
     * @var bool
     */
    protected $escapeOutput = false;

    public function initializeArguments(): void
    {
        $this->registerArgument('contentType', 'string', 'The content type slug to collect items for', true);
        $this->registerArgument('as', 'string', 'Name of the variable the collected items are assigned to', true);
        $this->registerArgument('orderBy', 'string', 'Item key to sort by. The special value "folderName" sorts by the item directory name', false, 'folderName');
        $this->registerArgument('orderDirection', 'string', 'Sort direction: ASC or DESC', false, 'ASC');
        $this->registerArgument('limit', 'int', 'Maximum number of items to expose after sorting. 0 disables the limit', false, 100);
    }

    public function render(): string
    {
        $items = $this->collectItems($this->arguments['contentType']);
        $items = $this->sortItems(
            $items,
            $this->arguments['orderBy'],
            $this->arguments['orderDirection']
        );
        $items = $this->limitItems($items, (int) $this->arguments['limit']);

        // Expose the collected items only within this tag, mirroring how
        // f:for scopes its iteration variable.
        $globalVariableProvider = $this->renderingContext->getVariableProvider();
        $localVariableProvider = new StandardVariableProvider();
        $this->renderingContext->setVariableProvider(
            new ScopedVariableProvider($globalVariableProvider, $localVariableProvider)
        );

        $localVariableProvider->add($this->arguments['as'], $items);
        $output = $this->renderChildren();

        $this->renderingContext->setVariableProvider($globalVariableProvider);

        return $output;
    }

    /**
     * Build the item arrays for a content type. Each item is the item's merged
     * variables plus the derived "folderName" and "url" keys.
     *
     * @return array<int, array<string, mixed>>
     */
    private function collectItems(string $contentType): array
    {
        // Reuse the build-wide content index instead of re-scanning the
        // content directory on every render.
        $repository = ContentUrlService::getInstance()->getRepository($contentType);
        if ($repository === null) {
            return [];
        }

        $items = [];
        /* @var ContentType $model */
        foreach ($repository->findAll() as $model) {
            $variables = $model->getVariables();

            // Derived keys help templates build links and labels without having
            // to repeat the folder name in every variables.php. They do not
            // override values the item already defines.
            $variables += [
                'folderName' => $model->getTitle(),
                'url' => $model->getPublicPath(),
            ];

            $items[] = $variables;
        }

        return $items;
    }

    /**
     * Keep only the first $limit items. A limit of 0 (or less) keeps all items.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function limitItems(array $items, int $limit): array
    {
        if ($limit <= 0) {
            return $items;
        }

        return array_slice($items, 0, $limit);
    }

    /**
     * Sort items by the given key. "folderName" and "url" are available
     * alongside every variables.php key. Unknown keys sort as empty.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function sortItems(array $items, string $orderBy, string $orderDirection): array
    {
        $descending = strtoupper($orderDirection) === 'DESC';

        usort($items, function (array $a, array $b) use ($orderBy, $descending) {
            $valueA = $a[$orderBy] ?? '';
            $valueB = $b[$orderBy] ?? '';

            if (is_string($valueA) && is_string($valueB)) {
                $comparison = strnatcasecmp($valueA, $valueB);
            } else {
                $comparison = $valueA <=> $valueB;
            }

            return $descending ? -$comparison : $comparison;
        });

        return $items;
    }
}
