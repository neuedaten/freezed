<?php

namespace Neuedaten\Freezed\Commands;

use Neuedaten\Freezed\Services\ServeService;

/**
 * `freezed serve` — start PHP's built-in web server for the public/ directory.
 */
class ServeCommand
{
    public function execute(): int
    {
        return ServeService::getInstance()->serveForeground();
    }
}
