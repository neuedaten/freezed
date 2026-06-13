<?php

namespace Neuedaten\Freezed\Services;

/**
 * Watches the project's source directories and rebuilds the site whenever a
 * file changes.
 *
 * Detection is done by polling file modification times (and the set of files
 * itself, so additions and deletions are noticed too). Polling needs no PHP
 * extension and works the same on every platform.
 *
 * Each rebuild runs in a *fresh* PHP subprocess (`freezed build`). Freezed makes
 * heavy use of singletons that cache state for the duration of a process;
 * re-running in a clean process guarantees the rebuild never sees stale data.
 */
class WatchService
{
    protected static self|null $instance = null;

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Run the watch loop. Blocks until interrupted (Ctrl+C).
     *
     * @param bool $initialBuild Build once before entering the loop. `run`
     *                           disables this because it builds itself first.
     */
    public function watch(bool $initialBuild = true): void
    {
        $log = LogService::getInstance();

        if ($initialBuild) {
            $this->triggerBuild();
        }

        $intervalMs = (int) ConfigService::getInstance()->getValue('[watch][intervalMs]');
        $lastSnapshot = $this->snapshot();

        $log->notice('Watching for changes... (Ctrl+C to stop)');

        while (true) {
            usleep($intervalMs * 1000);

            $snapshot = $this->snapshot();
            if ($snapshot === $lastSnapshot) {
                continue;
            }

            $lastSnapshot = $snapshot;
            $log->notice('Change detected, rebuilding...');
            $this->triggerBuild();
        }
    }

    /**
     * Run one build in a fresh subprocess, forwarding the original CLI options
     * (e.g. --enviroment:development, --log). Returns the build's exit code.
     */
    public function triggerBuild(): int
    {
        $configService = ConfigService::getInstance();

        $php = (string) ($configService->getValue('[cli][php]') ?: PHP_BINARY);
        $bin = (string) $configService->getValue('[cli][bin]');
        $optionArgs = $configService->getValue('[cli][optionArgs]') ?? [];
        $projectRoot = (string) $configService->getValue('[projectRoot]');

        $parts = array_merge([$php, $bin, 'build'], $optionArgs);
        $command = implode(' ', array_map('escapeshellarg', $parts));

        // Make sure the subprocess resolves the same project root.
        putenv('FREEZED_ROOT=' . $projectRoot);

        $exitCode = 0;
        passthru($command, $exitCode);

        return $exitCode;
    }

    /**
     * Build a map of watched file path => modification time. Comparing two
     * snapshots detects changes, additions and deletions.
     *
     * @return array<string, int>
     */
    private function snapshot(): array
    {
        $snapshot = [];

        foreach ($this->watchedDirectories() as $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(
                    $directory,
                    \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS
                )
            );

            foreach ($iterator as $file) {
                /* @var \SplFileInfo $file */
                if ($file->isFile()) {
                    $snapshot[$file->getPathname()] = $file->getMTime();
                }
            }
        }

        foreach ($this->watchedFiles() as $file) {
            if (is_file($file)) {
                $snapshot[$file] = filemtime($file);
            }
        }

        ksort($snapshot);

        return $snapshot;
    }

    /**
     * @return array<int, string>
     */
    private function watchedDirectories(): array
    {
        $configService = ConfigService::getInstance();
        $root = $configService->getValue('[projectRoot]');

        $keys = ['[contentPath]', '[themesPath]', '[staticPath]'];
        $directories = [];

        foreach ($keys as $key) {
            $relative = $configService->getValue($key);
            if ($relative) {
                $directories[] = $root . DIRECTORY_SEPARATOR . $relative;
            }
        }

        return $directories;
    }

    /**
     * @return array<int, string>
     */
    private function watchedFiles(): array
    {
        $root = ConfigService::getInstance()->getValue('[projectRoot]');

        return [
            $root . DIRECTORY_SEPARATOR . 'freezed.config.php',
        ];
    }
}
