<?php

namespace Neuedaten\Freezed\Domain\Model;

class Resource
{
    protected string $sourcePath = '';

    protected string $targetPath = '';

    protected string $publicPath = '';

    protected string $type = '';

    /** Short content hash of the source file, used for cache busting. */
    protected string $identifier = '';

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

    public function getPublicPath(): string
    {
        return $this->publicPath;
    }

    public function setPublicPath(string $publicPath): void
    {
        $this->publicPath = $publicPath;
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
