<?php

namespace Neuedaten\Freezed\Services;

use Symfony\Component\PropertyAccess\PropertyAccess;

class ConfigService {
    protected static self|null $instance = null;

    protected array $config = [];

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function __construct()
    {
        $this->readDefaultConfig();
    }

    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getValue(string $path): mixed
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        return $propertyAccessor->getValue($this->config, $path);
    }

    public function setValue(string $path, mixed $value): void
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $propertyAccessor->setValue($this->config, $path, $value);
    }

    private function readDefaultConfig(): void
    {

        $config = include __DIR__ . '/../../includes/config.php';
        $this->setConfig($config);
    }
}
