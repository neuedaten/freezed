<?php

namespace Neuedaten\Freezed\Domain\Model;

class Resource
{
    protected string $sourcePath = '';

    protected string $targetPath = '';

    protected string $publicPath = '';

    protected string $type = '';

    protected string $mimeType = '';

    protected string $originalName = '';

    protected string $convertedName = '';

    protected string $identifier = '';

    protected array $config = [];

    protected string $jsModuleName = '';

    public function getSourcePath(): string
    {
        return $this->sourcePath;
    }

    public function setSourcePath(string $sourcePath): void
    {
        $this->sourcePath = $sourcePath;
    }

    public function getTargetPath(): string
    {
        return $this->targetPath;
    }

    public function setTargetPath(string $targetPath): void
    {
        $this->targetPath = $targetPath;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function getPublicPath(): string
    {
        return $this->publicPath;
    }

    public function setPublicPath(string $publicPath): void
    {
        $this->publicPath = $publicPath;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): void
    {
        $this->originalName = $originalName;
    }

    public function getConvertedName(): string
    {
        return $this->convertedName;
    }

    public function setConvertedName(string $convertedName): void
    {
        $this->convertedName = $convertedName;
    }

    public function getJsModuleName(): string
    {
        return $this->jsModuleName;
    }

    public function setJsModuleName(string $jsModuleName): void
    {
        $this->jsModuleName = $jsModuleName;
    }

}
