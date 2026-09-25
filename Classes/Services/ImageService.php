<?php

namespace Neuedaten\Freezed\Services;

use Neuedaten\Freezed\Exception\PathNotAllowedException;

/**
 * Processes images for the freezed:image ViewHelper: resize, change format and
 * re-encode with a given quality.
 *
 * Processed files are cached on disk. The cache key is part of the generated
 * filename and covers the source content, the target dimensions and the
 * encoding quality, so a changed source image or a changed quality produces a
 * new file instead of silently reusing the old one. That same hash doubles as
 * the cache buster in the public URL. The cache lives outside public/ because
 * public/ is wiped on every build; the cached file is copied into public/ each
 * time.
 *
 * Generated files mirror the source's location below content/ or themes/
 * (public/images/pages/home/assets/hero_800x600_q80_a1b2c3d4.webp for
 * content/pages/home/assets/hero.jpg), the same way freezed:resource does, so
 * a same-named image in two content folders never collides with another.
 *
 * Imagick is used when available, otherwise GD. Source types that cannot be
 * decoded (e.g. SVG) are passed through unchanged.
 */
class ImageService
{
    protected static self|null $instance = null;

    /** Source image types we can decode and transform. */
    private const SUPPORTED_TYPES = ['jpeg', 'png', 'webp', 'gif'];

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Process a source image and return the public URL (leading slash) of the
     * generated file.
     *
     * @param string      $sourcePath Absolute path to the source image.
     * @param int|null    $width      Target width in px, or null for "auto".
     * @param int|null    $height     Target height in px, or null for "auto".
     * @param string|null $fileType   Output type (jpg|jpeg|png|webp|gif), or null to keep the source type.
     * @param int|null    $quality    Encoding quality for lossy formats, or null for the configured default.
     * @param bool        $scaleUp    Allow enlarging beyond the original size.
     */
    public function process(
        string $sourcePath,
        ?int $width = null,
        ?int $height = null,
        ?string $fileType = null,
        ?int $quality = null,
        bool $scaleUp = false
    ): string {
        $sourcePath = realpath($sourcePath) ?: $sourcePath;

        if (!is_file($sourcePath)) {
            LogService::getInstance()->add('Image source not found: ' . $sourcePath, LogService::TYPES['warning']);
            return '';
        }

        $imageInfo = @getimagesize($sourcePath);
        $sourceType = $imageInfo ? $this->imageTypeToName($imageInfo[2]) : null;

        // Unsupported source (e.g. SVG): pass the original through untouched,
        // unless it is an SVG that carries script.
        if ($sourceType === null || !in_array($sourceType, self::SUPPORTED_TYPES, true)) {
            if (FileService::svgContainsScript($sourcePath)) {
                LogService::getInstance()->warning(
                    'Image not published: ' . $sourcePath . ' is an SVG with script, event handlers or embedded HTML.'
                );
                return '';
            }

            return $this->passthrough($sourcePath);
        }

        $originalWidth = (int) $imageInfo[0];
        $originalHeight = (int) $imageInfo[1];

        $outputType = $this->normaliseType($fileType) ?? $sourceType;
        $quality = $quality ?? (int) (ConfigService::getInstance()->getValue('[imageDefaultQuality]') ?? 90);

        [$targetWidth, $targetHeight] = $this->calculateDimensions(
            $originalWidth,
            $originalHeight,
            $width,
            $height,
            $scaleUp
        );

        $extension = $this->extensionForType($outputType);
        $fileName = $this->buildFileName($sourcePath, $extension, $targetWidth, $targetHeight, $quality);

        $cacheFile = $this->cacheFile($fileName);
        if (!is_file($cacheFile)) {
            $this->createDirectory(dirname($cacheFile));
            $this->render($sourcePath, $sourceType, $cacheFile, $outputType, $targetWidth, $targetHeight, $quality);
        }

        return $this->publish($cacheFile, $fileName);
    }

    /**
     * Copy a source image into public/ unchanged (used for types we cannot
     * decode) and return its public URL.
     */
    private function passthrough(string $sourcePath): string
    {
        $fileName = $this->relativeName($sourcePath)
            . '_' . AssetVersionService::hash($sourcePath)
            . '.' . strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        $cacheFile = $this->cacheFile($fileName);
        if (!is_file($cacheFile)) {
            $this->createDirectory(dirname($cacheFile));
            copy($sourcePath, $cacheFile);
        }

        return $this->publish($cacheFile, $fileName);
    }

    /**
     * Copy a cached file into the public output directory and return its URL.
     */
    private function publish(string $cacheFile, string $fileName): string
    {
        $configService = ConfigService::getInstance();

        $assetsDirectory = trim((string) $configService->getValue('[assetsDirectory]'), '/');
        $imagePublicDirectory = trim((string) $configService->getValue('[imagePublicDirectory]'), '/');

        $relativePath = ltrim(
            ($assetsDirectory !== '' ? $assetsDirectory . '/' : '')
            . ($imagePublicDirectory !== '' ? $imagePublicDirectory . '/' : '')
            . $fileName,
            '/'
        );

        $publicRoot = ProjectPathsService::getInstance()->getRealPath('publicPath');
        $targetFile = FileService::resolvePathBelow($publicRoot, $relativePath, 'Image output "' . $relativePath . '"');

        $this->createDirectory(dirname($targetFile));
        copy($cacheFile, $targetFile);

        LogService::getInstance()->add('Image: ' . $cacheFile . ' to ' . $targetFile, LogService::TYPES['info']);

        return '/' . $relativePath;
    }

    /**
     * Compute the target dimensions, preserving the aspect ratio.
     *
     * - both auto: original size.
     * - one set: scale by that side.
     * - both set: fit inside the box (contain).
     * Unless $scaleUp is true, the image is never enlarged beyond its original.
     *
     * @return array{0:int,1:int}
     */
    private function calculateDimensions(
        int $originalWidth,
        int $originalHeight,
        ?int $width,
        ?int $height,
        bool $scaleUp
    ): array {
        if ($width === null && $height === null) {
            $ratio = 1.0;
        } elseif ($width !== null && $height === null) {
            $ratio = $width / $originalWidth;
        } elseif ($width === null && $height !== null) {
            $ratio = $height / $originalHeight;
        } else {
            $ratio = min($width / $originalWidth, $height / $originalHeight);
        }

        if ($ratio > 1.0 && !$scaleUp) {
            $ratio = 1.0;
        }

        return [
            max(1, (int) round($originalWidth * $ratio)),
            max(1, (int) round($originalHeight * $ratio)),
        ];
    }

    private function render(
        string $sourcePath,
        string $sourceType,
        string $targetPath,
        string $outputType,
        int $targetWidth,
        int $targetHeight,
        int $quality
    ): void {
        if (extension_loaded('imagick')) {
            $this->renderWithImagick($sourcePath, $targetPath, $outputType, $targetWidth, $targetHeight, $quality);
            return;
        }

        if (extension_loaded('gd')) {
            $this->renderWithGd($sourcePath, $sourceType, $targetPath, $outputType, $targetWidth, $targetHeight, $quality);
            return;
        }

        throw new \RuntimeException(
            'freezed:image requires the Imagick or GD PHP extension, but neither is loaded.'
        );
    }

    private function renderWithImagick(
        string $sourcePath,
        string $targetPath,
        string $outputType,
        int $targetWidth,
        int $targetHeight,
        int $quality
    ): void {
        $image = new \Imagick($sourcePath);
        $image->setImageColorspace(\Imagick::COLORSPACE_SRGB);
        $image->resizeImage($targetWidth, $targetHeight, \Imagick::FILTER_LANCZOS, 1);
        $image->setImageFormat($outputType);

        if (in_array($outputType, ['jpeg', 'webp'], true)) {
            $image->setImageCompressionQuality($quality);
        }

        $image->stripImage();
        $image->writeImage($targetPath);
        $image->clear();
        $image->destroy();
    }

    private function renderWithGd(
        string $sourcePath,
        string $sourceType,
        string $targetPath,
        string $outputType,
        int $targetWidth,
        int $targetHeight,
        int $quality
    ): void {
        $source = match ($sourceType) {
            'jpeg' => imagecreatefromjpeg($sourcePath),
            'png' => imagecreatefrompng($sourcePath),
            'webp' => imagecreatefromwebp($sourcePath),
            'gif' => imagecreatefromgif($sourcePath),
            default => false,
        };

        if ($source === false) {
            throw new \RuntimeException('Unable to decode image: ' . $sourcePath);
        }

        $target = imagecreatetruecolor($targetWidth, $targetHeight);

        // Preserve transparency for formats that support an alpha channel.
        if (in_array($outputType, ['png', 'webp', 'gif'], true)) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled(
            $target,
            $source,
            0, 0, 0, 0,
            $targetWidth, $targetHeight,
            imagesx($source), imagesy($source)
        );

        switch ($outputType) {
            case 'jpeg':
                imagejpeg($target, $targetPath, $quality);
                break;
            case 'webp':
                imagewebp($target, $targetPath, $quality);
                break;
            case 'png':
                // GD PNG quality is a 0-9 compression level; map roughly from quality.
                imagepng($target, $targetPath, (int) round((100 - $quality) / 11.1));
                break;
            case 'gif':
                imagegif($target, $targetPath);
                break;
        }
    }

    /**
     * Build the output path (relative to the image output directory) from the
     * source's location, original name, target resolution, quality and a short
     * hash of the source content, e.g.
     * "pages/home/assets/hero_800x600_q80_a1b2c3d4.webp". The path keeps
     * files from different content folders with the same basename apart, the
     * format is encoded in the extension, and scaleUp needs no part of its own
     * because it can only change the output by changing the dimensions.
     *
     * Everything that affects the result is therefore part of the name: the file
     * is both a correct cache key and a cache-busting public URL.
     */
    private function buildFileName(
        string $sourcePath,
        string $extension,
        int $targetWidth,
        int $targetHeight,
        int $quality
    ): string {
        return $this->relativeName($sourcePath)
            . '_' . $targetWidth . 'x' . $targetHeight
            . '_q' . $quality
            . '_' . AssetVersionService::hash($sourcePath)
            . '.' . $extension;
    }

    /**
     * Source path relative to its content or theme root, without extension and
     * with every segment slugified, e.g. "pages/home/assets/hero" for
     * "<project>/content/pages/home/assets/hero.jpg" or
     * "00_default/assets/images/hero" for a theme image. A source outside both
     * roots falls back to its parent folder and name.
     */
    private function relativeName(string $sourcePath): string
    {
        $relative = FileService::getPathWithoutThemeOrContentDirectory($sourcePath);

        if ($relative === $sourcePath) {
            $relative = basename(dirname($sourcePath)) . '/' . basename($sourcePath);
        }

        $relative = str_replace('\\', '/', $relative);
        $segments = explode('/', dirname($relative));
        $segments[] = pathinfo($relative, PATHINFO_FILENAME);

        $segments = array_filter(
            array_map(fn (string $segment): string => $this->slug($segment), $segments),
            fn (string $segment): bool => $segment !== '' && $segment !== '.'
        );

        return implode('/', $segments);
    }

    /**
     * Delete all cached processed images. Returns the number of files removed.
     *
     * The cache directory is validated by ProjectPathsService (inside the
     * project, not the project itself, not overlapping content, themes,
     * static or public), and symlinks are removed as links, never followed.
     */
    public function clearCache(): int
    {
        $directory = realpath($this->cacheDirectory());
        if ($directory === false || !is_dir($directory)) {
            return 0;
        }

        if (!FileService::isInside($directory, ProjectPathsService::getInstance()->getRealPath('imageCacheDirectory'))) {
            throw new PathNotAllowedException(
                'Refusing to flush "' . $directory . '": it is not the configured image cache.'
            );
        }

        return $this->clearDirectory($directory);
    }

    /**
     * Recursively delete the files below a directory (the cache mirrors the
     * source folder structure) and remove the emptied sub-directories. A
     * symlink is unlinked, whatever it points to stays untouched.
     */
    private function clearDirectory(string $directory): int
    {
        $deleted = 0;
        $entries = @scandir($directory) ?: [];

        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $entry = $directory . '/' . $name;

            if (is_link($entry) || is_file($entry)) {
                if (unlink($entry)) {
                    $deleted++;
                }
            } elseif (is_dir($entry)) {
                $deleted += $this->clearDirectory($entry);
                @rmdir($entry);
            }
        }

        return $deleted;
    }

    private function cacheDirectory(): string
    {
        return ProjectPathsService::getInstance()->getRealPath('imageCacheDirectory');
    }

    /**
     * Absolute path of a cache file, kept below the cache directory.
     */
    private function cacheFile(string $fileName): string
    {
        return FileService::resolvePathBelow($this->cacheDirectory(), $fileName, 'Image cache file "' . $fileName . '"');
    }

    private function createDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, (int) (ConfigService::getInstance()->getValue('[mkdirPermissions]') ?? 0777), true);
        }
    }

    /**
     * Slugify one path segment: lower-case, whitespace to "-", anything but
     * a-z, 0-9, "-" and "_" dropped. Dashes and underscores are kept so that
     * folders like "news-1" and "news1" stay distinct.
     */
    private function slug(string $segment): string
    {
        return preg_replace('/\s+/', '-', preg_replace('/[^a-z0-9\s_-]/', '', strtolower($segment)));
    }

    /** Normalise a user-supplied file type to an internal type name, or null. */
    private function normaliseType(?string $fileType): ?string
    {
        if ($fileType === null || $fileType === '') {
            return null;
        }

        $fileType = strtolower($fileType);

        return $fileType === 'jpg' ? 'jpeg' : $fileType;
    }

    /** File extension for an internal type name (jpeg -> jpg). */
    private function extensionForType(string $type): string
    {
        return $type === 'jpeg' ? 'jpg' : $type;
    }

    private function imageTypeToName(int $imageType): ?string
    {
        return match ($imageType) {
            IMAGETYPE_JPEG => 'jpeg',
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_GIF => 'gif',
            default => null,
        };
    }
}
