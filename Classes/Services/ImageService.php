<?php

namespace Neuedaten\Freezed\Services;

/**
 * Processes images for the freezed:image ViewHelper: resize, change format and
 * re-encode with a given quality.
 *
 * Processed files are cached on disk (keyed by source + modification time +
 * processing parameters) so they are only generated once and reused on
 * subsequent builds. The cache lives outside public/ because public/ is wiped
 * on every build; the cached file is copied into public/ each time.
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

        // Unsupported source (e.g. SVG): pass the original through untouched.
        if ($sourceType === null || !in_array($sourceType, self::SUPPORTED_TYPES, true)) {
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
        $fileName = $this->buildFileName($sourcePath, $extension, $targetWidth, $targetHeight);

        $cacheFile = $this->cacheDirectory() . '/' . $fileName;
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
        $fileName = $this->nameSlug($sourcePath)
            . '.' . strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        $cacheFile = $this->cacheDirectory() . '/' . $fileName;
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

        $publicRoot = $configService->getValue('[projectRoot]') . '/' . $configService->getValue('[publicPath]');
        $targetFile = $publicRoot . '/' . $relativePath;

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

        imagedestroy($source);
        imagedestroy($target);
    }

    /**
     * Build the output filename from the source folder, original name and target
     * resolution, e.g. "images-hero_800x600.webp". The folder name keeps files
     * from different directories with the same basename apart. The format is
     * encoded in the extension; other parameters (quality, scaleUp) and source
     * changes are not part of the name, so run `cache:flush` after changing those.
     */
    private function buildFileName(
        string $sourcePath,
        string $extension,
        int $targetWidth,
        int $targetHeight
    ): string {
        return $this->nameSlug($sourcePath)
            . '_' . $targetWidth . 'x' . $targetHeight
            . '.' . $extension;
    }

    /**
     * Slug combining the source's parent folder and filename, e.g.
     * "images-hero" for ".../assets/images/hero.jpg".
     */
    private function nameSlug(string $sourcePath): string
    {
        $folder = $this->slug(basename(dirname($sourcePath)));
        $name = $this->slug(pathinfo($sourcePath, PATHINFO_FILENAME));

        return ($folder !== '' ? $folder . '-' : '') . $name;
    }

    /**
     * Delete all cached processed images. Returns the number of files removed.
     */
    public function clearCache(): int
    {
        $directory = $this->cacheDirectory();
        if (!is_dir($directory)) {
            return 0;
        }

        $deleted = 0;
        foreach ((array) glob($directory . '/*') as $file) {
            if (is_file($file) && unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    private function cacheDirectory(): string
    {
        $configService = ConfigService::getInstance();

        return $configService->getValue('[projectRoot]') . '/'
            . trim((string) $configService->getValue('[imageCacheDirectory]'), '/');
    }

    private function createDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, (int) (ConfigService::getInstance()->getValue('[mkdirPermissions]') ?? 0777), true);
        }
    }

    /** Slugify a filename the same way resources do. */
    private function slug(string $filename): string
    {
        return preg_replace('/\s+/', '-', preg_replace('/[^a-z0-9\s]/', '', strtolower($filename)));
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
