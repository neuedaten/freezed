<?php

namespace Neuedaten\Freezed\ViewHelpers;

use Neuedaten\Freezed\Services\ContentUrlService;
use Neuedaten\Freezed\Services\LogService;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper;

/**
 * Renders an <a> tag, in the spirit of TYPO3's f:link.
 *
 * "href" accepts an absolute URL (https://…, mailto:, tel:, //host), a relative
 * or root-relative URL (passed through unchanged), or a content reference
 * "CONTENT:<contentType>/<itemFolder>" that is resolved at build time to the
 * item's public path (from the content type's targetDirectory and the item's
 * output filename; index.<ext> collapses to the directory URL).
 *
 *     <freezed:link href="CONTENT:pages/impressum" class="footer__link">Imprint</freezed:link>
 *     <freezed:link href="CONTENT:pages/features" section="pricing" absolute="true">Pricing</freezed:link>
 *     <freezed:link href="https://example.org" target="_blank">External</freezed:link>
 *
 * Arguments:
 *   href       Absolute URL, relative URL or "CONTENT:<type>/<folder>" reference (required).
 *   section    Anchor appended as "#section".
 *   absolute   Prefix root-relative URLs with "siteUrl" from freezed.config.php (default false).
 *
 * Any other attribute (class, target, rel, title, id, data-*, aria-*, …) is
 * passed through to the tag. A target="_blank" link without an explicit rel
 * gets rel="noopener".
 *
 * When a CONTENT: reference does not match any item, no link is created:
 * a <span class="dead-link"> with the same content and attributes is rendered
 * instead and a warning is logged. The build never fails because of it.
 */
class LinkViewHelper extends AbstractTagBasedViewHelper
{
    /**
     * @var string
     */
    protected $tagName = 'a';

    /** Attributes that only make sense on an <a>; dropped on the dead-link span. */
    private const LINK_ONLY_ATTRIBUTES = ['href', 'target', 'rel', 'download', 'hreflang', 'ping', 'referrerpolicy', 'type'];

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('href', 'string', 'Absolute URL, relative URL or "CONTENT:<type>/<folder>" reference', true);
        $this->registerArgument('section', 'string', 'Anchor appended as #section', false);
        $this->registerArgument('absolute', 'bool', 'Prefix the URL with siteUrl from freezed.config.php', false, false);
    }

    public function render(): string
    {
        $href = trim((string) $this->arguments['href']);
        $absolute = (bool) $this->arguments['absolute'];
        $section = $this->arguments['section'] ?? null;

        $urlService = ContentUrlService::getInstance();

        if (ContentUrlService::isReference($href)) {
            $model = $urlService->resolveReference($href);
            if ($model === null) {
                return $this->renderDeadLink($href);
            }
            $url = $urlService->getUrl($model, $absolute, $section);
        } else {
            $url = $urlService->decorate($href, $absolute, $section);
        }

        // "href" is a registered argument and therefore not added to the tag
        // automatically like the pass-through attributes are.
        $this->tag->addAttribute('href', $url);

        if ($this->tag->getAttribute('target') === '_blank' && !$this->tag->hasAttribute('rel')) {
            $this->tag->addAttribute('rel', 'noopener');
        }

        return $this->renderTag();
    }

    /**
     * Render a <span class="dead-link"> instead of a link for an unresolvable
     * content reference. Pass-through attributes are kept (minus link-only
     * ones) so styling hooks like class, title or data-* still apply.
     */
    private function renderDeadLink(string $reference): string
    {
        if (ContentUrlService::getInstance()->markDeadLinkReported($reference)) {
            LogService::getInstance()->warning(sprintf(
                'Dead link: "%s" does not match any content item (first seen in %s).',
                $reference,
                $this->describeCurrentTemplate()
            ));
        }

        // initialize() has already applied the pass-through attributes and the
        // tag name; both can only be changed here, inside render().
        $this->tag->setTagName('span');
        foreach (self::LINK_ONLY_ATTRIBUTES as $attributeName) {
            $this->tag->removeAttribute($attributeName);
        }

        // Read the raw class value: getAttribute() would return it escaped and
        // addAttribute() escapes again.
        $userClass = trim((string) ($this->additionalArguments['class'] ?? ''));
        $this->tag->addAttribute('class', trim('dead-link ' . $userClass));

        return $this->renderTag();
    }

    private function renderTag(): string
    {
        // Children are already escaped by Fluid where necessary; setContent()
        // takes the markup verbatim. Force a closing tag so an empty body does
        // not collapse to a self-closing tag.
        $this->tag->setContent((string) $this->renderChildren());
        $this->tag->forceClosingTag(true);

        return $this->tag->render();
    }

    /**
     * Short name of the content item being rendered, e.g. "pages/about". The
     * item's directory is the last template root path RenderService registers.
     */
    private function describeCurrentTemplate(): string
    {
        $rootPaths = $this->renderingContext->getTemplatePaths()->getTemplateRootPaths();
        $itemDirectory = rtrim((string) end($rootPaths), '/');

        if ($itemDirectory === '') {
            return 'unknown template';
        }

        return basename(dirname($itemDirectory)) . '/' . basename($itemDirectory);
    }
}
