<?php

namespace Neuedaten\Freezed\Services;

/**
 * Background processes next to the dev server: `freezed run --desk` starts
 * the registered command "desk" this way. Output goes to the same console,
 * the process is terminated when `run` ends.
 */
class ProcessService
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
     * Start a registered command (`freezed <name>`) in the background.
     *
     * @return array{proc: resource, pipes: array, name: string}
     */
    public function startCommand(string $name): array
    {
        $configService = ConfigService::getInstance();
        $php = (string) ($configService->getValue('[cli][php]') ?: PHP_BINARY);
        $bin = (string) $configService->getValue('[cli][bin]');
        $projectRoot = (string) $configService->getValue('[projectRoot]');

        putenv('FREEZED_ROOT=' . $projectRoot);

        $process = proc_open(
            [$php, $bin, $name],
            [
                0 => ['file', 'php://stdin', 'r'],
                1 => ['file', 'php://stdout', 'w'],
                2 => ['file', 'php://stderr', 'w'],
            ],
            $pipes,
            $projectRoot
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start "freezed ' . $name . '".');
        }

        LogService::getInstance()->notice('Started freezed ' . $name . ' in the background');

        return ['proc' => $process, 'pipes' => $pipes, 'name' => $name];
    }

    /**
     * @param array{proc: resource, pipes: array, name?: string} $handle
     */
    public function stop(array $handle): void
    {
        foreach ($handle['pipes'] as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }

        if (isset($handle['proc']) && is_resource($handle['proc'])) {
            proc_terminate($handle['proc']);
            proc_close($handle['proc']);
        }
    }
}
