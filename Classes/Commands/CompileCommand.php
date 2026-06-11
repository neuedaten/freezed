<?php

namespace Neuedaten\Freezed\Commands;

use Neuedaten\Freezed\Services\CompileService;
use Neuedaten\Freezed\Services\RunScriptService;

class CompileCommand {

    public function execute(): void
    {
        RunScriptService::getInstance()->runScriptsByEvent('start');

        $compileService = CompileService::getInstance();
        $compileResult = $compileService->compile();
        echo $compileResult;

        RunScriptService::getInstance()->runScriptsByEvent('end');
    }
}
