<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Commands\CommandInterface;

/**
 * Commands that packages and the project add to the CLI.
 *
 * A package declares them in its composer.json:
 *
 *     "extra": {
 *         "freezed": {
 *             "commands": {
 *                 "desk": "Neuedaten\\FreezedDesk\\Commands\\ServeCommand",
 *                 "desk:show": "Neuedaten\\FreezedDesk\\Commands\\ShowCommand"
 *             }
 *         }
 *     }
 *
 * The project's freezed.config.php may add or override them with a
 * "commands" key of the same shape; null removes a command. Every class
 * implements CommandInterface. `freezed <name>` looks a name up here when
 * it is not a built-in command, and `freezed run --<name>` starts the
 * command as a background process next to the dev server.
 */
class CommandRegistryService
{
    /** Names of the built-in commands; a package cannot take them. */
    public const BUILT_IN = ['install', 'init', 'build', 'compile', 'serve', 'watch', 'run', 'cache:flush', 'help', 'version'];

    protected static self|null $instance = null;

    /** @var array<string, string>|null name => class */
    protected ?array $commands = null;

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    public function reset(): void
    {
        $this->commands = null;
    }

    /**
     * @return array<string, string> name => class, sorted by name.
     */
    public function all(): array
    {
        if ($this->commands !== null) {
            return $this->commands;
        }

        $commands = [];

        foreach ($this->packageDeclarations() as $name => $class) {
            $commands[$name] = $class;
        }

        $configured = ConfigService::getInstance()->getValue('[commands]');
        if (is_array($configured)) {
            foreach ($configured as $name => $class) {
                if ($class === null || $class === false) {
                    unset($commands[(string) $name]);
                    continue;
                }
                if (is_string($class)) {
                    $commands[(string) $name] = ltrim($class, '\\');
                }
            }
        }

        foreach (array_keys($commands) as $name) {
            if (!preg_match('/^[a-z][a-z0-9:_-]*$/', $name) || in_array($name, self::BUILT_IN, true)) {
                LogService::getInstance()->warning(sprintf('Ignoring registered command "%s": not a valid name or a built-in command.', $name));
                unset($commands[$name]);
            }
        }

        ksort($commands);

        return $this->commands = $commands;
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    /**
     * An instance of the registered command, or null when the name is not
     * registered.
     *
     * @throws \RuntimeException When the class is missing or does not implement CommandInterface.
     */
    public function get(string $name): ?CommandInterface
    {
        $class = $this->all()[$name] ?? null;
        if ($class === null) {
            return null;
        }

        if (!class_exists($class)) {
            throw new \RuntimeException(sprintf('Command "%s" is registered with the class "%s", which does not exist. Check the package\'s autoloading.', $name, $class));
        }
        if (!is_subclass_of($class, CommandInterface::class)) {
            throw new \RuntimeException(sprintf('Command "%s": class "%s" must implement %s.', $name, $class, CommandInterface::class));
        }

        return new $class();
    }

    /**
     * extra.freezed.commands of every installed package, the root package
     * included, read through Composer's runtime data.
     *
     * @return array<string, string>
     */
    private function packageDeclarations(): array
    {
        if (!class_exists(\Composer\InstalledVersions::class)) {
            return [];
        }

        $commands = [];
        $seen = [];

        foreach (\Composer\InstalledVersions::getAllRawData() as $data) {
            foreach ($data['versions'] ?? [] as $package => $info) {
                $installPath = $info['install_path'] ?? null;
                if (!is_string($installPath) || $installPath === '') {
                    continue;
                }
                $file = rtrim($installPath, '/\\') . '/composer.json';
                $real = realpath($file);
                if ($real === false || isset($seen[$real])) {
                    continue;
                }
                $seen[$real] = true;

                $json = json_decode((string) @file_get_contents($real), true);
                $declared = $json['extra']['freezed']['commands'] ?? null;
                if (!is_array($declared)) {
                    continue;
                }
                foreach ($declared as $name => $class) {
                    if (is_string($class) && $class !== '') {
                        $commands[(string) $name] = ltrim($class, '\\');
                    }
                }
            }
        }

        return $commands;
    }
}
