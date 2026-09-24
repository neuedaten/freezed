<?php

namespace Neuedaten\Freezed\Domain\Source;

/**
 * Supplies the items of a content type.
 *
 * By default a content type reads its items from the folders below
 * content/<type>/ (see DirectoryContentSource). A content type can be switched
 * to any other source in freezed.config.php:
 *
 *     'entries' => [
 *         'targetDirectory' => 'entries',
 *         'targetFileExtension' => 'html',
 *         'source' => \App\Content\EntrySource::class,   // or 'data/entries.php', 'data/entries.json'
 *     ],
 *
 * The folder content/<type>/ then only holds the templates (index.html, plus
 * any per-item templates named by the "template" key) and shared assets. All
 * data comes from the source, and everything after that -- variables, output
 * files, CONTENT: links, contentTypeCollection, the sitemap -- works exactly
 * as for folder-based items.
 *
 * Every item is an array with these keys:
 *
 *   slug       Required. Identifies the item like a folder name would:
 *              it becomes the output file name (slug.<ext>, unless the
 *              variables set "targetFileName"), the "folderName" key in
 *              contentTypeCollection and the target of CONTENT:<type>/<slug>.
 *              May contain "/" to build nested output paths; must not contain
 *              ".." segments.
 *   variables  Optional. The item's variables, merged on top of the site-wide
 *              and the content type's variables like a variables.php would be.
 *   template   Optional. Template name inside content/<type>/, without
 *              extension. Defaults to "index".
 */
interface ContentSourceInterface
{
    /**
     * All items of the given content type.
     *
     * @param string $typeSlug          The content type slug, e.g. "entries".
     * @param array  $contentTypeConfig The type's configuration from freezed.config.php.
     * @return iterable<int, array{slug: string, variables?: array<string, mixed>, template?: string}>
     */
    public function findAll(string $typeSlug, array $contentTypeConfig): iterable;

    /**
     * A value that changes whenever the source's data changes, e.g. the
     * newest updated_at of a database table or a row count. `freezed watch`
     * polls it and rebuilds when it differs. Return null when the source
     * cannot tell; watch then only reacts to file changes.
     */
    public function getVersion(string $typeSlug, array $contentTypeConfig): ?string;
}
