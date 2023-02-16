<?php declare(strict_types=1);

namespace Quextum\Images\Pipes;


use Nette;
use Nette\Utils\FileSystem;
use Nette\Utils\Image as NImage;
use Quextum\Images\FileNotFoundException;
use Quextum\Images\Handlers\IImageHandler;
use Quextum\Images\Handlers\ImageException;
use Quextum\Images\Handlers\ImageFactory;
use Quextum\Images\Request;
use Quextum\Images\Result;
use Quextum\Images\Utils\BarDumpLogger;
use Quextum\Images\Utils\Helpers;
use Quextum\Images\Utils\SourceImage;
use Tracy\ILogger;

/**
 * @method onBeforeSave(IImageHandler $img, string $thumbnailPath, string $image, $width, $height, int|string|null $flags)
 * @method onAfterSave(string $thumbnailPath)
 */
class LazyImagePipe implements IImagePipe
{
    use Nette\SmartObject;

    public array $onBeforeSave;
    public array $onAfterSave;

    protected string $assetsDir;
    protected string $sourceDir;
    protected string $path;
    protected ImageFactory $factory;
    protected ILogger $logger;
    protected array $quality;

    public function __construct(string $assetsDir, string $sourceDir, string $wwwDir, string $handlerClass, array $quality, Nette\Http\Request $httpRequest)
    {
        $this->sourceDir = $sourceDir;
        $this->assetsDir = $assetsDir;
        $this->quality = $quality;
        $this->path = rtrim($httpRequest->url->basePath, '/') . str_replace($wwwDir, '', $this->assetsDir);
        $this->factory = new ImageFactory($handlerClass);
        $this->setLogger(new BarDumpLogger());
    }

    public function setLogger(ILogger $logger): void
    {
        $this->logger = $logger;
    }

    public function getAssetsDir(): string
    {
        return $this->assetsDir;
    }

    public function getSourceDir(): string
    {
        return $this->sourceDir;
    }

    public function process(Request $request): Result
    {
        $image = $request->image;
        if (empty($image)) {
            return new Result('#');
            // throw new Nette\InvalidArgumentException('Image not specified');
        }
        /** @var SourceImage $image */
        if (!isset($image->width, $image->height, $image->mimeType)) {
            throw new Nette\InvalidArgumentException('Insufficient image info. Width, Height and MimeType are necessary.');
        }

        $size = $request->size;
        $flags = $request->flags;
        $format = $request->format;
        $options = $request->options;
        $strictMode = $request->strictMode;

        $originalFile = $this->getOriginalFile($image->path);
        if (file_exists($originalFile)) {
            $hash = hash_file('crc32b', $originalFile);
        } elseif ($strictMode) {
            throw new FileNotFoundException($originalFile);
        } else {
            $this->logger->log("Image not found: $originalFile");
            return new Result('#');
        }
        Helpers::transformFlags($flags);

        [$width, $height] = self::parseSize($size);
        $thumbPath = $this->getThumbnailPath($image->path, $width, $height, $options, $format, $flags, $hash);
        $thumbnailFile = $this->assetsDir . '/' . $thumbPath;


        if ($flags === 'crop') {
            $targetWidth = $width;
            $targetHeight = $height;
        } elseif ($width || $height) {
            if ($flags & NImage::EXACT && (!$height || !$width)) {
                [$width, $height] = NImage::calculateSize($image->width, $image->height, (int)$width, (int)$height, NImage::FIT | NImage::SHRINK_ONLY);
            }
            [$targetWidth, $targetHeight] = NImage::calculateSize($image->width, $image->height, (int)$width, (int)$height, $flags);
        } else {
            $targetWidth = $image->width;
            $targetHeight = $image->height;
        }


        $ready = file_exists($thumbnailFile);

        if (!$ready) {
            if (file_exists($originalFile)) {
                Helpers::callbackAfterRequest(function () use ($thumbnailFile, $originalFile, $width, $height, $targetWidth, $targetHeight, $image, $format, $options, $flags) {
                    try {
                        $img = $this->factory->create($originalFile);
                        if ($flags === 'crop') {
                            $img->crop('50%', '50%', $targetWidth, $targetHeight);
                        } elseif ($width || $height) {
                            $img->resize($targetWidth, $targetHeight, $flags, $options);
                        }
                        $this->onBeforeSave($img, $thumbnailFile, $image->path, $targetWidth, $targetHeight, $flags);
                        FileSystem::createDir(dirname($thumbnailFile));
                        $img->save($thumbnailFile, $options['quality'] ?? $this->quality[$format] ?? $this->quality['default'], $format);
                        $this->onAfterSave($thumbnailFile);
                    } catch (ImageException $exception) {
                        $this->logger->log($exception);
                    }
                });
            } elseif ($strictMode) {
                throw new FileNotFoundException("File '$originalFile' not found");
            } else {
                $this->logger->log("Image not found: $image $originalFile ");
            }
        }

        $mimeType = match ($format) {
            null => $image->mimeType,
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'avif' => 'image/avif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
        };

        return Result::from($this->getPath() . '/' . $thumbPath, $originalFile, $thumbnailFile, [
            0 => $targetWidth,
            1 => $targetHeight,
            'width' => $targetWidth,
            'height' => $targetHeight
        ], $mimeType)->setReady($ready);
    }


    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    protected function getOriginalFile(string $image)
    {
        return $this->sourceDir . '/' . $image;
    }

    /**
     * @param array|string|null $size
     * @return array|null[]|string[]
     */
    public static function parseSize(array|string|int|null $size): array
    {
        [$width, $height] = ((is_array($size) ? $size : explode('x', (string)$size)) + [null, null]);
        return [$width ?: null, $height ?: null];
    }

    /**
     * @throws \JsonException
     */
    protected function getThumbnailPath(string $image, $width, $height, $options, $format, $flags, $hash): string
    {
        $spec = ($width || $height) ? '_' . ($height ? $width . 'x' . $height : $width) : null;
        if ($options) {
            $spec .= '_' . substr((string)crc32(json_encode($options, JSON_THROW_ON_ERROR)), 0, 4);
        }
        $info = pathinfo($image);
        $dirname = trim($info['dirname'], './');
        $dirname = $dirname ? $dirname . '/' : '';
        $filename = $info['filename'];
        $ext = $format ? Nette\Utils\Strings::lower($format) : $info['extension'];
        return Helpers::webalizePath($dirname . $filename . '_' . $flags . $spec . '.' . $hash . '.' . $ext);
    }


}
