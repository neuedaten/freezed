<?php

namespace Neuedaten\Freezed\ViewHelpers;

use Neuedaten\Freezed\Domain\Model\ContentType;
use Neuedaten\Freezed\Services\ContentUrlService;
use TYPO3Fluid\Fluid\Core\Parser\BooleanParser;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\BooleanNode;
use TYPO3Fluid\Fluid\Core\Variables\ScopedVariableProvider;
use TYPO3Fluid\Fluid\Core\Variables\StandardVariableProvider;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * Collects all items of a given content type and exposes them as an array to
 * the child template, e.g. for teaser lists or menus.
 *
 * Each item holds every key from the item's variables (its variables.php, or
 * the "variables" of an item from a content source), plus two derived keys:
 * "folderName" (the item directory name or slug) and "url" (the public path the
 * item is built to, index.<ext> collapsing to the directory URL). The "as"
 * variable is only available inside the tag. "limit" caps the number of items
 * after sorting (default 100, 0 = no limit).
 *
 * "filter" keeps only items for which a boolean expression holds. It uses the
 * same syntax as the f:if condition; %key% placeholders stand for the item's
 * values (dot paths reach into nested arrays) and stay unquoted, like Fluid
 * variables in f:if. A single "=" is accepted as "==".
 *
 *     <freezed:contentTypeCollection contentType="cases" filter="%category% == 'News' && !%hidden%" orderBy="title" orderDirection="DESC" limit="5" as="items">
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
        $this->registerArgument('filter', 'string', 'Boolean expression in f:if syntax that every item must satisfy. %key% placeholders are replaced by the item\'s values, e.g. "%category% == \'News\'"', false, '');
    }

    public function render(): string
    {
        $items = $this->collectItems($this->arguments['contentType']);
        $items = $this->filterItems($items, (string) $this->arguments['filter']);
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
     * Keep only the items for which the filter expression evaluates to true.
     * An empty filter keeps everything.
     *
     * The expression is evaluated by Fluid's own BooleanParser, so it accepts
     * exactly what an f:if condition accepts: ==, !=, <, >, <=, >=, %, !,
     * && / and, || / or, parentheses, quoted strings, numbers, true/false.
     * Item values enter the expression through %key% placeholders. They are
     * swapped for {nodeN} context references before parsing, mirroring how
     * BooleanNode hands variables to the parser, so the values keep their
     * type instead of being pasted into the expression as text.
     *
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function filterItems(array $items, string $filter): array
    {
        $filter = $this->normalizeEqualityOperator(trim($filter));
        if ($filter === '') {
            return $items;
        }

        $parser = new BooleanParser();
        $kept = [];
        foreach ($items as $item) {
            [$expression, $context] = $this->bindPlaceholders($filter, $item);

            try {
                $result = $parser->evaluate($expression, $context);
            } catch (\Throwable $exception) {
                // Not chained on purpose: the build log reports the innermost
                // cause, and that should name the filter as written in the
                // template rather than the rewritten {nodeN} form.
                throw new Exception(sprintf(
                    'contentTypeCollection: invalid filter expression "%s" (%s)',
                    $filter,
                    $exception->getMessage()
                ), 1758000000);
            }

            if (BooleanNode::convertToBoolean($result, $this->renderingContext)) {
                $kept[] = $item;
            }
        }

        return $kept;
    }

    /**
     * Replace every %key% (or %key.path%) placeholder by a {nodeN} reference
     * and collect the referenced item values in a context array.
     *
     * Unknown keys resolve to null, so "%missing% == ''" and "!%missing%" both
     * hold, matching how f:if treats undefined variables.
     *
     * @param array<string, mixed> $item
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function bindPlaceholders(string $filter, array $item): array
    {
        $context = [];
        $counter = 0;

        $expression = preg_replace_callback(
            '/%([A-Za-z0-9_.\-]+)%/',
            function (array $match) use ($item, &$context, &$counter): string {
                $reference = 'node' . $counter++;
                $context[$reference] = $this->resolvePath($item, $match[1]);

                return '{' . $reference . '}';
            },
            $filter
        );

        return [$expression, $context];
    }

    /**
     * Read a dot-separated path from the item array, e.g. "meta.category".
     */
    private function resolvePath(array $item, string $path): mixed
    {
        $value = $item;
        foreach (explode('.', $path) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }

            if (is_object($value) && isset($value->{$segment})) {
                $value = $value->{$segment};
                continue;
            }

            return null;
        }

        return $value;
    }

    /**
     * Accept a lone "=" as "==" so "filter="%category% = News"" reads naturally.
     * Quoted strings are left untouched; ==, ===, !=, !==, <= and >= are not
     * affected either.
     */
    private function normalizeEqualityOperator(string $filter): string
    {
        return (string) preg_replace_callback(
            '/\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*"|(?<![=!<>])=(?!=)/',
            static fn (array $match): string => $match[0] === '=' ? '==' : $match[0],
            $filter
        );
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
     * alongside every variables.php key. Unknown keys sort as empty. Two
     * numeric values compare as numbers (so "0.25" sorts before "0.5" and
     * "-10" before "-5"), two strings in natural order, anything else with
     * PHP's standard comparison.
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

            if (is_numeric($valueA) && is_numeric($valueB)) {
                $comparison = (float) $valueA <=> (float) $valueB;
            } elseif (is_string($valueA) && is_string($valueB)) {
                $comparison = strnatcasecmp($valueA, $valueB);
            } else {
                $comparison = $valueA <=> $valueB;
            }

            return $descending ? -$comparison : $comparison;
        });

        return $items;
    }
}
