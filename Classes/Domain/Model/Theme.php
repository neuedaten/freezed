<?php

namespace Neuedaten\Freezed\Domain\Model;

use Neuedaten\Freezed\Services\ConfigService;

class Theme {

    protected string $path = '';

    public function getTemplateRootPath(): string|false {
        $path = $this->path . ConfigService::getInstance()->getValue('[themeTemplatesPath]');
        if (is_dir($path)) {
            return $path;
        }
        return false;
    }

    public function getLayoutRootPath(): string|false {
        $path = $this->path . ConfigService::getInstance()->getValue('[themeLayoutsPath]');
        if (is_dir($path)) {
            return $path;
        }
        return false;
    }

    public function getPartialRootPath(): string|false {
        $path = $this->path . ConfigService::getInstance()->getValue('[themePartialsPath]');
        if (is_dir($path)) {
            return $path;
        }
        return false;
    }

    public function getComponentRootPath(): string|false {
        $path = $this->path . ConfigService::getInstance()->getValue('[themeComponentsPath]');
        if (is_dir($path)) {
            return $path;
        }
        return false;
    }

    public function getStaticPath(): string|false {
        $path = $this->path . ConfigService::getInstance()->getValue('[themeStaticPath]');
        if (is_dir($path)) {
            return $path;
        }
        return false;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
    }


}
