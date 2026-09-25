<?php

namespace Neuedaten\Freezed\Commands;

use Neuedaten\Freezed\Exception\ScriptException;
use Neuedaten\Freezed\Services\ConfigService;
use Neuedaten\Freezed\Services\FileService;
use Neuedaten\Freezed\Services\LogService;
use Neuedaten\Freezed\Services\ProjectPathsService;
use Neuedaten\Freezed\Services\RunScriptService;

class InstallCommand
{

    /**
     * @return int Process exit code: 0 on success, 1 when a hook fails.
     */
    public function execute(): int
    {
        $log = LogService::getInstance();

        try {
            RunScriptService::getInstance()->runScriptsByEvent('beforeInstall');
        } catch (ScriptException $exception) {
            $log->error($exception->getMessage());
            return 1;
        }

        $fileService = new FileService();
        $paths = ProjectPathsService::getInstance();

        foreach (['contentPath', 'publicPath', 'staticPath', 'themesPath'] as $key) {
            $this->createDirectoryIfNotExist($paths->getLexicalPath($key));
        }

        /* copy default themes */
        $this->copyDirectoryItemsIfTargetDirectoryEmpty(
            realpath(__DIR__ . '/../../assets/themes/'),
            realpath($paths->getLexicalPath('themesPath'))
        );

        /* copy content type pages if folder content is empty */
        $this->copyDirectoryItemsIfTargetDirectoryEmpty(
            realpath(__DIR__ . '/../../assets/content/'),
            realpath($paths->getLexicalPath('contentPath'))
        );

        /* copy config file: */
        $fileService->copyFileIfTargetNotExists(
            __DIR__ . '/../../assets/freezed.config.php',
            $paths->getProjectRoot() . '/freezed.config.php'
        );

        try {
            RunScriptService::getInstance()->runScriptsByEvent('afterInstall');
        } catch (ScriptException $exception) {
            $log->error($exception->getMessage());
            return 1;
        }

        return 0;
    }

    private function createDirectoryIfNotExist(string $path): void
    {
        $permissions = ConfigService::getInstance()
            ->getValue('[mkdirPermissions]');

        if (!is_dir($path)) {
            mkdir($path, $permissions, true);
        }
    }

    private function copyDirectoryItemsIfTargetDirectoryEmpty(
        $sourcePath,
        $targetPath
    ): void {
        if (!is_dir($sourcePath) || !is_dir($targetPath)
            || count(scandir($targetPath)) !== 2
        ) {
            return;
        }

        (new FileService())->copyDirectoryItems($sourcePath, $targetPath);
    }
}
