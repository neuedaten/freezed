<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Exception\ScriptException;

/**
 * Runs the shell commands configured under "scripts" in freezed.config.php,
 * e.g. 'start' and 'end' around a build, 'beforeInstall' and 'afterInstall'
 * around freezed install.
 */
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

    /**
     * Run one shell command from the project root, with its output on the
     * console. A command that cannot be started or exits with a non-zero
     * status stops the build.
     *
     * @throws ScriptException
     */
    public function runScript(string $script): int
    {
        $projectRoot = ProjectPathsService::getInstance()->getProjectRoot();
        LogService::getInstance()->add('Script: ' . $script);

        // The command is a shell line by design (pipes, && and so on are
        // allowed); the working directory is set by proc_open itself, so a
        // project path with spaces or shell characters needs no quoting.
        $process = proc_open(
            $script,
            [
                0 => ['file', 'php://stdin', 'r'],
                1 => ['file', 'php://stdout', 'w'],
                2 => ['file', 'php://stderr', 'w'],
            ],
            $pipes,
            $projectRoot
        );

        if (!is_resource($process)) {
            throw new ScriptException('Could not start script "' . $script . '".');
        }

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new ScriptException(sprintf('Script "%s" failed with exit code %d.', $script, $exitCode));
        }

        return $exitCode;
    }
}
