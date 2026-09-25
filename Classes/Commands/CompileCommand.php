<?php

namespace Neuedaten\Freezed\Commands;

use Neuedaten\Freezed\Exception\ContentSourceException;
use Neuedaten\Freezed\Exception\PathNotAllowedException;
use Neuedaten\Freezed\Exception\ScriptException;
use Neuedaten\Freezed\Exception\TemplateRenderException;
use Neuedaten\Freezed\Services\CompileService;
use Neuedaten\Freezed\Services\LogService;
use Neuedaten\Freezed\Services\RunScriptService;

class CompileCommand {

    /**
     * Run a single build.
     *
     * @return int Process exit code: 0 on success, 1 when a template fails,
     *             a content source is unusable, a path breaks the file rule
     *             or a build hook fails.
     */
    public function execute(): int
    {
        $log = LogService::getInstance();

        try {
            RunScriptService::getInstance()->runScriptsByEvent('start');
            $result = CompileService::getInstance()->compile();
        } catch (TemplateRenderException $exception) {
            // Concise, actionable output: which template, which error.
            $log->error('Template "' . $exception->getTemplate() . '" failed to render.');
            $log->error($exception->getMessage());
            return 1;
        } catch (ContentSourceException | PathNotAllowedException | ScriptException $exception) {
            // A misconfigured source, a file outside the allowed roots or a
            // failed hook: the message already names the culprit.
            $log->error($exception->getMessage());
            return 1;
        }

        $log->success(sprintf(
            'Built %d page%s (%d file%s, %d resource%s%s) in %.0f ms',
            $result['pages'],
            $result['pages'] === 1 ? '' : 's',
            $result['files'],
            $result['files'] === 1 ? '' : 's',
            $result['resources'],
            $result['resources'] === 1 ? '' : 's',
            !empty($result['sitemap']) ? ', sitemap' : '',
            $result['durationMs']
        ));

        try {
            RunScriptService::getInstance()->runScriptsByEvent('end');
        } catch (ScriptException $exception) {
            $log->error($exception->getMessage());
            return 1;
        }

        return 0;
    }
}
