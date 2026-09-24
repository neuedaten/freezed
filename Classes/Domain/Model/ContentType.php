<?php

namespace Neuedaten\Freezed\Domain\Model;

class ContentType
{
    /** Template name rendered when an item names none. */
    public const DEFAULT_TEMPLATE = 'index';

    protected string $typeSlug;

    protected string $title;

    protected array $config;

    protected array $variables;

    protected string $content;

    protected string $directoryPath;

    protected string $targetDirectoryName;

    protected string $targetFileName;

    protected string $targetFileExtension;

    /** Template name (without extension) inside the item's template root. */
    protected string $template = self::DEFAULT_TEMPLATE;

    /** True when the item lives in a folder of its own (content/<type>/<slug>/). */
    protected bool $ownDirectory = true;


    public function __construct(string $typeSlug, string $title, string $directoryPath, array $config = []) {
        $this->typeSlug = $typeSlug;
        $this->title = $title;
        $this->directoryPath = $directoryPath;
        $this->config = $config;
    }

    public function getTypeSlug(): string
    {
        return $this->typeSlug;
    }

    public function setTypeSlug(string $typeSlug): void
    {
        $this->typeSlug = $typeSlug;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getDirectoryPath(): string
    {
        return $this->directoryPath;
    }

    public function setDirectoryPath(string $directoryPath): void
    {
        $this->directoryPath = $directoryPath;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setTemplate(string $template): void
    {
        $this->template = $template;
    }

    /**
     * True when the item's template root is a folder of its own
     * (content/<type>/<slug>/), false for items from another source, which
     * share the content type's folder.
     */
    public function hasOwnDirectory(): bool
    {
        return $this->ownDirectory;
    }

    public function setOwnDirectory(bool $ownDirectory): void
    {
        $this->ownDirectory = $ownDirectory;
    }

    public function getTargetDirectoryName(): string
    {
        return $this->targetDirectoryName;
    }

    public function setTargetDirectoryName(string $targetDirectoryName): void
    {
        $this->targetDirectoryName = $targetDirectoryName;
    }

    public function getTargetFileName(): string
    {
        return $this->targetFileName;
    }

    public function setTargetFileName(string $targetFileName): void
    {
        $this->targetFileName = $targetFileName;
    }

    public function getTargetFileExtension(): string
    {
        return $this->targetFileExtension;
    }

    public function setTargetFileExtension(string $targetFileExtension): void
    {
        $this->targetFileExtension = $targetFileExtension;
    }

    public function getTargetFileNameWithExtension(): string
    {
        if (array_key_exists('targetFileName', $this->variables)) {
            return $this->variables['targetFileName'];
        }

        return $this->targetFileName . '.' . $this->targetFileExtension;
    }

    /**
     * Root-relative public path this item is built to, e.g. "/cases/case2.html".
     *
     * A target file named index.<ext> collapses to its directory URL: "/" for
     * the root, "/cases/" for targetDirectory "cases", and "/cases/first/" for
     * a targetFileName "first/index.html" that points into a sub-folder.
     */
    public function getPublicPath(): string
    {
        $directory = trim(str_replace('\\', '/', $this->targetDirectoryName), '/');
        $fileName = $this->getTargetFileNameWithExtension();

        $base = '/' . ($directory !== '' ? $directory . '/' : '');

        return $base . preg_replace('/(^|\/)index\.[a-z0-9]+$/i', '$1', $fileName);
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function setVariables(array $variables): void
    {
        $this->variables = $variables;
    }


}
