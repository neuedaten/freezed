<?php

namespace Neuedaten\Freezed\Commands;

use Neuedaten\Freezed\Services\ImageService;
use Neuedaten\Freezed\Services\LogService;

/**
 * `freezed cache:flush` — remove cached processed images so they are
 * regenerated on the next build.
 */
class CacheFlushCommand
{
    public function execute(): int
    {
        $deleted = ImageService::getInstance()->clearCache();

        LogService::getInstance()->success(sprintf(
            'Flushed image cache (%d file%s).',
            $deleted,
            $deleted === 1 ? '' : 's'
        ));

        return 0;
    }
}
