<?php

namespace Neuedaten\Freezed\Commands;

use Neuedaten\Freezed\Services\CommandRegistryService;
use Neuedaten\Freezed\Services\ConfigService;
use Neuedaten\Freezed\Services\LogService;
use Neuedaten\Freezed\Services\ProcessService;
use Neuedaten\Freezed\Services\ServeService;
use Neuedaten\Freezed\Services\WatchService;

/**
 * `freezed run` — development mode: build once, serve the result, and rebuild
 * on every change.
 *
 * The web server runs as a background process while the file watcher stays in
 * the foreground. A flag named like a registered command (`--desk`) starts
 * that command in the background as well. On Ctrl+C everything is shut down
 * cleanly.
 */
class RunCommand
{
    /** @var array{proc: resource, pipes: array}|null */
    private ?array $serverHandle = null;

    /** @var array<int, array{proc: resource, pipes: array, name: string}> */
    private array $processHandles = [];

    public function execute(): int
    {
        $log = LogService::getInstance();
        $watch = WatchService::getInstance();
        $serve = ServeService::getInstance();

        // 1) Initial build so the server has something to serve.
        $watch->triggerBuild();

        // 2) Start the web server in the background.
        $this->serverHandle = $serve->startBackground();

        // 2b) Registered commands asked for by flag, e.g. --desk.
        $buildConfig = ConfigService::getInstance()->getValue('[buildConfig]') ?? [];
        foreach (array_keys(CommandRegistryService::getInstance()->all()) as $name) {
            if (($buildConfig[$name] ?? null) === true) {
                $this->processHandles[] = ProcessService::getInstance()->startCommand($name);
            }
        }

        // 3) Make sure the server is stopped when this process ends.
        $this->registerShutdownHandlers($log, $serve);

        // 4) Watch and rebuild in the foreground (no extra initial build).
        $watch->watch(false);

        return 0;
    }

    private function registerShutdownHandlers(LogService $log, ServeService $serve): void
    {
        $stop = function () use ($log, $serve): void {
            foreach ($this->processHandles as $handle) {
                $log->notice('Stopping freezed ' . $handle['name'] . '...');
                ProcessService::getInstance()->stop($handle);
            }
            $this->processHandles = [];

            if ($this->serverHandle !== null) {
                $log->notice('Stopping server...');
                $serve->stop($this->serverHandle);
                $this->serverHandle = null;
            }
        };

        // Always stop the server on normal shutdown.
        register_shutdown_function($stop);

        // React to Ctrl+C (SIGINT) and SIGTERM when pcntl is available.
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            $handler = function () use ($stop): void {
                $stop();
                exit(0);
            };
            pcntl_signal(SIGINT, $handler);
            pcntl_signal(SIGTERM, $handler);
        }
    }
}
