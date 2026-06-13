<?php

namespace Neuedaten\Freezed\Services;

/**
 * Wraps PHP's built-in web server (php -S) so a Freezed project can be previewed
 * without an external server. Equivalent to:
 *
 *     php -S <host>:<port> -t <publicPath>
 *
 * Host and port come from CLI options (--host, --port) and fall back to the
 * values configured in includes/config.php.
 */
class ServeService
{
    protected static self|null $instance = null;

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function getHost(): string
    {
        $build = $this->buildConfig();
        return (string) ($build['host'] ?? ConfigService::getInstance()->getValue('[serve][host]'));
    }

    public function getPort(): int
    {
        $build = $this->buildConfig();
        return (int) ($build['port'] ?? ConfigService::getInstance()->getValue('[serve][port]'));
    }

    public function getDocumentRoot(): string
    {
        $configService = ConfigService::getInstance();
        return $configService->getValue('[projectRoot]') . DIRECTORY_SEPARATOR
            . $configService->getValue('[publicPath]');
    }

    /**
     * Start the server in the foreground. Blocks until the server is stopped
     * (e.g. with Ctrl+C). Used by `freezed serve`.
     */
    public function serveForeground(): int
    {
        $host = $this->getHost();
        $port = $this->getPort();
        $docRoot = $this->getDocumentRoot();

        LogService::getInstance()->notice(
            sprintf('Serving %s at http://%s:%d (Ctrl+C to stop)', $docRoot, $host, $port)
        );

        $command = implode(' ', array_map('escapeshellarg', [
            $this->phpBinary(), '-S', $host . ':' . $port, '-t', $docRoot,
        ]));

        $exitCode = 0;
        passthru($command, $exitCode);

        return $exitCode;
    }

    /**
     * Start the server as a background process. Used by `freezed run`, which
     * keeps the watch loop in the foreground.
     *
     * @return array{proc: resource, pipes: array} A handle to pass to stop().
     */
    public function startBackground(): array
    {
        $host = $this->getHost();
        $port = $this->getPort();
        $docRoot = $this->getDocumentRoot();

        $descriptors = [
            0 => ['file', 'php://stdin', 'r'],
            1 => ['file', 'php://stdout', 'w'],
            2 => ['file', 'php://stderr', 'w'],
        ];

        $process = proc_open(
            [$this->phpBinary(), '-S', $host . ':' . $port, '-t', $docRoot],
            $descriptors,
            $pipes
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('Could not start the built-in PHP server.');
        }

        LogService::getInstance()->notice(
            sprintf('Serving %s at http://%s:%d', $docRoot, $host, $port)
        );

        return ['proc' => $process, 'pipes' => $pipes];
    }

    /**
     * Stop a background server previously started with startBackground().
     *
     * @param array{proc: resource, pipes: array} $handle
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

    private function phpBinary(): string
    {
        return (string) (ConfigService::getInstance()->getValue('[cli][php]') ?: PHP_BINARY);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildConfig(): array
    {
        return ConfigService::getInstance()->getValue('[buildConfig]') ?? [];
    }
}
