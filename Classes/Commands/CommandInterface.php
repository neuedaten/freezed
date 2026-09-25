<?php

namespace Neuedaten\Freezed\Commands;

/**
 * A command that a package or a project registers with the CLI (see
 * CommandRegistryService). It runs after the project configuration has been
 * loaded into the ConfigService and logging has been configured, but before
 * the core has validated the project's directories: a registered command
 * calls ProjectPathsService::getInstance()->validate() itself, once it has
 * done whatever it needs to do beforehand (creating a folder the project
 * declares, for instance).
 */
interface CommandInterface
{
    /**
     * @param string[]             $args    Positional arguments after the command name.
     * @param array<string, mixed> $options The parsed --key:value options.
     * @return int Exit code.
     */
    public function execute(array $args, array $options): int;
}
