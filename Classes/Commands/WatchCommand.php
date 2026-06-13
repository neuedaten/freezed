<?php

namespace Neuedaten\Freezed\Commands;

use Neuedaten\Freezed\Services\WatchService;

/**
 * `freezed watch` — rebuild the site whenever a source file changes.
 *
 * Runs until interrupted (Ctrl+C).
 */
class WatchCommand
{
    public function execute(): int
    {
        WatchService::getInstance()->watch(true);

        return 0;
    }
}
