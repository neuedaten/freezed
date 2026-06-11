<?php

namespace Neuedaten\Freezed\Services;

class RunScriptService
{
    protected static self|null $instance = null;

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function runScriptsByEvent(string $event): void
    {
        $configService = ConfigService::getInstance();
        $scripts = $configService->getValue('[scripts][' . $event . ']');

        if (!$scripts) {
            return;
        }

        foreach ($scripts as $script) {
            $this->runScript($script);
        }
    }

    public function runScript(string $script): int
    {
        $output = [];
        $returnVar = 0;
        $projectRoot = ConfigService::getInstance()->getValue('[projectRoot]');
        exec('cd ' . $projectRoot . ' && ' . $script, $output, $returnVar);
        LogService::getInstance()->add('Script: ' . $script);

        return $returnVar;
    }
}
