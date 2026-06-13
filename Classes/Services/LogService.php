<?php

namespace Neuedaten\Freezed\Services;

/**
 * Central logging service.
 *
 * All output produced by Freezed should go through this service. It decides,
 * based on the message type and the configured verbosity, what is shown on the
 * CLI and (optionally) mirrors *everything* into a log file.
 *
 * Message types and their CLI behaviour:
 *  - error    -> STDERR, always (even in quiet mode)
 *  - warning  -> STDOUT, shown unless quiet
 *  - success  -> STDOUT, shown unless quiet (user-facing result, e.g. build summary)
 *  - notice   -> STDOUT, shown unless quiet (user-facing status, e.g. "watching...")
 *  - info     -> STDOUT, detail noise, shown only with --verbose
 *
 * The log file (enabled via --log or --log=path) always receives every entry,
 * regardless of the CLI verbosity.
 */
class LogService {
    protected static self|null $instance = null;

    const TYPES = [
        'info' => 'info',
        'notice' => 'notice',
        'success' => 'success',
        'warning' => 'warning',
        'error' => 'error',
    ];

    /** @var array<int, array{message: string, type: string, level: int, time: float}> */
    protected array $logs = [];

    /** @var resource|null Open file handle when a log file is configured. */
    protected $fileHandle = null;

    /** Show info-level detail on the CLI. */
    protected bool $verbose = false;

    /** Suppress everything except errors on the CLI. */
    protected bool $quiet = false;

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Configure verbosity and the optional log file from parsed CLI options.
     *
     * Recognised options:
     *  --verbose            Show info-level detail on the CLI.
     *  --quiet              Only show errors on the CLI.
     *  --log                Write the full log to <projectRoot>/freezed.log.
     *  --log=path/file.log  Write the full log to the given path (relative paths
     *                       are resolved against the project root).
     *
     * @param array<string, mixed> $buildConfig
     */
    public function configureFromBuildConfig(array $buildConfig, string $projectRoot): void
    {
        $this->verbose = !empty($buildConfig['verbose']);
        $this->quiet = !empty($buildConfig['quiet']);

        if (!array_key_exists('log', $buildConfig)) {
            return;
        }

        $value = $buildConfig['log'];
        $path = ($value === true || $value === '') ? 'freezed.log' : (string) $value;

        // Resolve relative paths against the project root.
        if (!$this->isAbsolutePath($path)) {
            $path = rtrim($projectRoot, '/\\') . DIRECTORY_SEPARATOR . $path;
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        $handle = @fopen($path, 'a');
        if ($handle === false) {
            // Fall back to a warning on the CLI but keep going without a file.
            $this->add('Could not open log file: ' . $path, self::TYPES['warning']);
            return;
        }

        $this->fileHandle = $handle;
        $this->add('Logging to ' . $path, self::TYPES['info']);
    }

    public function add(string $message, string $type = self::TYPES['info'], int $level = 0): void
    {
        $entry = [
            'message' => $message,
            'type' => $type,
            'level' => $level,
            'time' => microtime(true),
        ];

        $this->logs[] = $entry;
        $this->writeToFile($entry);
        $this->print($entry);
    }

    /** Convenience helpers for the most common, user-facing message types. */
    public function info(string $message): void
    {
        $this->add($message, self::TYPES['info']);
    }

    public function notice(string $message): void
    {
        $this->add($message, self::TYPES['notice']);
    }

    public function success(string $message): void
    {
        $this->add($message, self::TYPES['success']);
    }

    public function warning(string $message): void
    {
        $this->add($message, self::TYPES['warning']);
    }

    public function error(string $message): void
    {
        $this->add($message, self::TYPES['error']);
    }

    /**
     * Render an entry to the appropriate CLI stream, respecting verbosity.
     */
    public function print(array $entry): void
    {
        $type = $entry['type'];

        if (!$this->shouldPrint($type)) {
            return;
        }

        $stream = $type === self::TYPES['error'] ? STDERR : STDOUT;
        fwrite($stream, $this->formatForCli($entry) . PHP_EOL);
    }

    public function getAll(): array
    {
        return $this->logs;
    }

    /**
     * Decide whether an entry of the given type is shown on the CLI.
     */
    private function shouldPrint(string $type): bool
    {
        if ($type === self::TYPES['error']) {
            return true; // errors are always shown
        }

        if ($this->quiet) {
            return false; // quiet hides everything but errors
        }

        if ($type === self::TYPES['info']) {
            return $this->verbose; // info is detail, only with --verbose
        }

        return true; // notice, success, warning
    }

    private function formatForCli(array $entry): string
    {
        return match ($entry['type']) {
            self::TYPES['error'] => 'Error: ' . $entry['message'],
            self::TYPES['warning'] => 'Warning: ' . $entry['message'],
            default => $entry['message'],
        };
    }

    private function writeToFile(array $entry): void
    {
        if ($this->fileHandle === null) {
            return;
        }

        $line = sprintf(
            '[%s] %-7s %s%s',
            date('Y-m-d H:i:s', (int) $entry['time']),
            strtoupper($entry['type']),
            $entry['message'],
            PHP_EOL
        );

        fwrite($this->fileHandle, $line);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/')
            || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path); // Windows drive letter
    }

    public function __destruct()
    {
        if (is_resource($this->fileHandle)) {
            fclose($this->fileHandle);
        }
    }
}
