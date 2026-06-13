<?php

namespace Neuedaten\Freezed\Commands;

use Neuedaten\Freezed\Exception\TemplateRenderException;
use Neuedaten\Freezed\Services\CompileService;
use Neuedaten\Freezed\Services\LogService;
use Neuedaten\Freezed\Services\RunScriptService;

class CompileCommand {

    /**
     * Run a single build.
     *
     * @return int Process exit code: 0 on success, 1 when a template fails.
     */
    public function execute(): int
    {
        $log = LogService::getInstance();

        RunScriptService::getInstance()->runScriptsByEvent('start');

        try {
            $result = CompileService::getInstance()->compile();
        } catch (TemplateRenderException $exception) {
            // Concise, actionable output: which template, which error.
            $log->error('Template "' . $exception->getTemplate() . '" failed to render.');
            $log->error($exception->getMessage());
            return 1;
        }

        $log->success(sprintf(
            'Built %d page%s (%d file%s, %d resource%s) in %.0f ms',
            $result['pages'],
            $result['pages'] === 1 ? '' : 's',
            $result['files'],
            $result['files'] === 1 ? '' : 's',
            $result['resources'],
            $result['resources'] === 1 ? '' : 's',
            $result['durationMs']
        ));

        RunScriptService::getInstance()->runScriptsByEvent('end');

        return 0;
    }
}
