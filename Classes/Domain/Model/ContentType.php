<?php

namespace Neuedaten\Freezed\Domain\Model;

class ContentType
{

    protected string $typeSlug;

    protected string $title;

    protected array $config;

    protected array $variables;

    protected string $content;

    protected string $directoryPath;

    protected string $targetDirectoryName;

    protected string $targetFileName;

    protected string $targetFileExtension;


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

    public function getVariables(): array
    {
        return $this->variables;
    }

    public function setVariables(array $variables): void
    {
        $this->variables = $variables;
    }


}
