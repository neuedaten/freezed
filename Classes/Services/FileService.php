<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Domain\Model\Resource;

class FileService
{

    protected string $targetDirectory;

    public function __construct()
    {
        $this->targetDirectory = ConfigService::getInstance()
                ->getValue('[projectRoot]') . '/' . ConfigService::getInstance()
                ->getValue('[publicPath]');
    }

    public function clearTargetDirectory(): void
    {
        $this->clearDirectoryContent($this->targetDirectory, true);
    }

    public function clearDirectoryContent(
        string $path,
        bool $recursive = true
    ): void {

        /* check if path is inside this projects: */

        $path = realpath($path);
        if (!$path) {
            throw new \Exception('Path does not exist');
        }

        $projectRoot = ConfigService::getInstance()->getValue('[projectRoot]');
        if (!$projectRoot || !str_starts_with($path, $projectRoot)) {
            throw new \Exception('Path is not inside project root');
        }

        $files = glob($path . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                LogService::getInstance()->add('Delete file: ' . $file,
                    LogService::TYPES['info']);
            }
            if ($recursive && is_dir($file)) {
                $this->clearDirectoryContent($file, true);
                rmdir($file);
                LogService::getInstance()->add('Delete directory: ' . $file,
                    LogService::TYPES['info']);
            }
        }
    }

    public function writeFile(string $path, string $content): void
    {
        $path = $this->targetDirectory . '/' . $path;
        // Ensure the target subdirectory exists (e.g. public/cases/ for a
        // content type with a non-empty targetDirectory).
        $this->createDirectoryIfNotExist(dirname($path));
        file_put_contents($path, $content);
        LogService::getInstance()->add('Write file: ' . $path,
            LogService::TYPES['info']);
    }

    public function copyResource(Resource $resource): void
    {
//        $targetPath = self::virtualRealpath($this->targetDirectory . '/'
//            . ConfigService::getInstance()
//                ->getValue('[compile][assetsTargetDirectory]') . '/' . $resource->getConvertedName());

        $targetPath = self::virtualRealpath(implode(DIRECTORY_SEPARATOR, [
            $this->targetDirectory,
            ConfigService::getInstance()
                ->getValue('[assetsDirectory]'),
            $resource->getTargetPath()
        ]));

        $this->createDirectoryIfNotExist(dirname($targetPath));

        copy($resource->getSourcePath(), $targetPath);
        LogService::getInstance()->add('Copy resource: '
            . $resource->getSourcePath() . ' to ' . $targetPath,
            LogService::TYPES['info']);
    }

    private function createDirectoryIfNotExist(string $path): void
    {
        $permissions = ConfigService::getInstance()
            ->getValue('[mkdirPermissions]');

        if (!is_dir($path)) {
            mkdir($path, $permissions, true);
        }
    }

    public function copyDirectoryItems(string $source, string $target): void
    {
        $source = self::virtualRealpath($source);
        $target = self::virtualRealpath($target);

        $this->createDirectoryIfNotExist($target);

        $files = glob($source . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $targetFile = $target . '/' . basename($file);
                copy($file, $targetFile);
                LogService::getInstance()->add('Copy file: ' . $file . ' to '
                    . $targetFile, LogService::TYPES['info']);
            }
            if (is_dir($file)) {
                $targetDirectory = $target . '/' . basename($file);
                $this->copyDirectoryItems($file, $targetDirectory);
            }
        }
    }

    public function copyFileIfTargetNotExists(
        string $source,
        string $target
    ): void {
        $source = realpath($source);
        $target = self::virtualRealpath($target);

        if (!$source || realpath($target)) {
            return;
        }

        if (!is_file($target)) {
            copy($source, $target);
            LogService::getInstance()->add('Copy file: ' . $source . ' to '
                . $target, LogService::TYPES['info']);
        }
    }


    static function virtualRealpath($path): string
    {
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $resultPathParts = [];
        foreach ($parts as $part) {
            if ('.' == $part) {
                continue;
            }
            if ('..' == $part) {
                array_pop($resultPathParts);
            } else {
                $resultPathParts[] = $part;
            }
        }

        foreach ($resultPathParts as $index => $part) {
            if ($part === '' && $index > 0) {
                unset($resultPathParts[$index]);
            }
        }

        return implode(DIRECTORY_SEPARATOR, $resultPathParts);
    }

    static function getPathWithoutThemeOrContentDirectory(string $path): string
    {
        $configService = ConfigService::getInstance();
        $themesPath = $configService->getValue('[projectRoot]') . '/'
            . $configService->getValue('[themesPath]');
        $contentPath = $configService->getValue('[projectRoot]') . '/'
            . $configService->getValue('[contentPath]');

        if (str_starts_with($path, $themesPath)) {
            return str_replace($themesPath, '', $path);
        }

        if (str_starts_with($path, $contentPath)) {
            return str_replace($contentPath, '', $path);
        }

        return $path;
    }

}
