<?php

namespace Neuedaten\Freezed\Commands;

use Neuedaten\Freezed\Services\ConfigService;
use Neuedaten\Freezed\Services\FileService;
use Neuedaten\Freezed\Services\RunScriptService;

class InstallCommand
{

    public function execute(): int
    {
        RunScriptService::getInstance()->runScriptsByEvent('beforeInstall');

        $fileService = new FileService();
        $configService = ConfigService::getInstance();

        $directories = [
            $fileService::virtualRealpath($configService->getValue('[projectRoot]')
                . '/' . $configService->getValue('[contentPath]')),
            $fileService::virtualRealpath($configService->getValue('[projectRoot]')
                . '/' . $configService->getValue('[publicPath]')),
            $fileService::virtualRealpath($configService->getValue('[projectRoot]')
                . '/' . $configService->getValue('[staticPath]')),
            $fileService::virtualRealpath($configService->getValue('[projectRoot]')
                . '/' . $configService->getValue('[themesPath]'))
        ];

        foreach ($directories as $directory) {
            $this->createDirectoryIfNotExist($directory);
        }

        /* copy default themes */
        $this->copyDirectoryItemsIfTargetDirectoryEmpty(
            realpath(__DIR__ . '/../../assets/themes/'),
            realpath($configService->getValue('[projectRoot]') . '/'
                . $configService->getValue('[themesPath]'))
        );

        /* copy content type pages if folder content is empty */
        $this->copyDirectoryItemsIfTargetDirectoryEmpty(
            realpath(__DIR__ . '/../../assets/content/'),
            realpath($configService->getValue('[projectRoot]') . '/'
                . $configService->getValue('[contentPath]'))
        );

        /* copy config file: */
        $fileService->copyFileIfTargetNotExists(
            __DIR__ . '/../../assets/freezed.config.php',
            $configService->getValue('[projectRoot]') . '/freezed.config.php'
        );

        RunScriptService::getInstance()->runScriptsByEvent('afterInstall');

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

        $fileService = new FileService();
        $configService = ConfigService::getInstance();
        if (is_dir($targetPath) && count(scandir($targetPath)) === 2) {
            $fileService->copyDirectoryItems($sourcePath, $targetPath);
        }
    }
}
