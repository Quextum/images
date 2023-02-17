<?php

namespace Quextum\Images\Pipes;

use Nette;
use Nette\Utils\FileSystem;
use Nette\Utils\Image as NImage;
use Quextum\Images\FileNotFoundException;
use Quextum\Images\Handlers\ImageException;
use Quextum\Images\Handlers\ImageHandler;
use Quextum\Images\Handlers\ImageHandlerFactory;
use Quextum\Images\Request;
use Quextum\Images\Result;
use Quextum\Images\Utils\BarDumpLogger;
use Quextum\Images\Utils\Helpers;
use Tracy\ILogger;

/**
 * @method onBeforeSave(ImageHandler $img, string $thumbnailPath, string $image, $width, $height, int|string|null $flags)
 * @method onAfterSave(string $thumbnailPath)
 */
class ImagePipe implements IImagePipe
{
    use Nette\SmartObject;

    public array $onBeforeSave;
    public array $onAfterSave;

    protected string $assetsDir;
    protected string $sourceDir;
    protected string $path;
    protected ImageHandlerFactory $factory;
    protected ILogger $logger;
    protected array $quality;

    public function __construct(string $assetsDir, string $sourceDir, string $wwwDir, ImageHandlerFactory $factory, array $quality,
                                ILogger $logger,
                                Nette\Http\Request $httpRequest
    )
    {
        $this->sourceDir = $sourceDir;
        $this->assetsDir = $assetsDir;
        $this->quality = $quality;
        $this->path = rtrim($httpRequest->url->basePath, '/') . str_replace($wwwDir, '', $this->assetsDir);
        $this->factory = $factory;
        $this->setLogger($logger);
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
        $image = (string)$request->image;
        if (empty($image)) {
            throw new Nette\InvalidArgumentException('Image not specified');
        }
        $size = $request->size;
        $flags = $request->flags;
        $format = $request->format;
        $options = $request->options;
        $strictMode = $request->strictMode;

        $originalFile = $this->getOriginalFile($image);
        if (file_exists($originalFile)) {
            $hash = hash_file('crc32b', $originalFile);
        } elseif ($strictMode) {
            throw new FileNotFoundException($originalFile);
        } else {
            $this->logger->log("Image not found: $originalFile");
            return new Result(null);
        }
        Helpers::transformFlags($flags);

        [$width, $height] = self::parseSize($size);
        $thumbPath = $this->getThumbnailPath($image, $width, $height, $options, $format, $flags, $hash);
        $thumbnailFile = $this->assetsDir . '/' . $thumbPath;
        if (!file_exists($thumbnailFile)) {
            if (file_exists($originalFile)) {
                try {
                    $img = $this->factory->create($originalFile);
                    if ($flags === 'crop') {
                        $img->crop('50%', '50%', $width, $height);
                    } elseif ($width || $height) {
                        if ($flags & NImage::EXACT && (!$height || !$width)) {
                            [$width, $height] = NImage::calculateSize($img->getWidth(), $img->getHeight(), (int)$width, (int)$height, NImage::FIT | NImage::SHRINK_ONLY);
                        }
                        $img->resize($width, $height, $flags, $options);
                    }
                    $this->onBeforeSave($img, $thumbnailFile, $image, $width, $height, $flags);
                    FileSystem::createDir(dirname($thumbnailFile));
                    $img->save($thumbnailFile, $options['quality'] ?? $this->quality[$format] ?? $this->quality['default'], $format);
                    $this->onAfterSave($thumbnailFile);
                } catch (ImageException $exception) {
                    if ($strictMode) {
                        throw new FileNotFoundException("Unable to create image from '$originalFile'", previous: $exception);
                    }
                    $this->logger->log($exception);
                }
            } elseif ($strictMode) {
                throw new FileNotFoundException("File '$originalFile' not found");
            } else {
                $this->logger->log("Image not found: $image $originalFile ");
            }
        }
        return new Result($this->getPath() . '/' . $thumbPath, $originalFile, $thumbnailFile, (@getimagesize($thumbnailFile)) ?: null, @mime_content_type($thumbnailFile) ?: null);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    protected function getOriginalFile(string $image): string
    {
        return $this->sourceDir . '/' . $image;
    }

    /**  @return null[]|string[] */
    public static function parseSize(array|string|null $size): array
    {
        [$width, $height] = ((is_array($size) ? $size : explode('x', (string)$size)) + [null, null]);
        return [$width ?: null, $height ?: null];
    }

    protected function getThumbnailPath(string $image, $width, $height, $options, $format, $flags, $hash): string
    {
        $spec = ($width || $height) ? '_' . ($height ? $width . 'x' . $height : $width) : null;
        if ($options) {
            $spec .= '_' . substr(crc32(json_encode($options)), 0, 4);
        }
        $info = pathinfo($image);
        $dirname = trim($info['dirname'], './');
        $dirname = $dirname ? $dirname . '/' : '';
        $filename = $info['filename'];
        $ext = $format ? Nette\Utils\Strings::lower($format) : $info['extension'];
        return Helpers::webalizePath($dirname . $filename . '_' . $flags . $spec . '.' . $hash . '.' . $ext);
    }


}
