<?php

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
use Tracy\ILogger;

/**
 * @author Jan Brabec <brabijan@gmail.com>
 * @method onBeforeRequest(Request $request)
 * @method onAfterRequest(Request $request, Result $result)
 * @method onBeforeSave(IImageHandler $img, string $thumbnailPath, string $image, $width, $height, int|string|null $flags)
 * @method onAfterSave(string $thumbnailPath)
 */
class ImagePipe
{
    use Nette\SmartObject;

    public const FLAGS = [
        'fit' => NImage::FIT,
        'fill' => NImage::FILL,
        'exact' => NImage::EXACT,
        'shrink' => NImage::SHRINK_ONLY,
        'stretch' => NImage::STRETCH,
    ];

    public array $onBeforeRequest;
    public array $onAfterRequest;
    public array $onBeforeSave;
    public array $onAfterSave;

    protected string $assetsDir;
    protected string $sourceDir;
    protected string $path;
    protected ImageFactory $factory;
    protected ILogger $logger;
    protected array $quality;

    /**
     * @param string $assetsDir
     * @param string $sourceDir
     * @param string $wwwDir
     * @param string $handlerClass
     * @param array $quality
     * @param Nette\Http\Request $httpRequest
     */
    public function __construct(string $assetsDir, string $sourceDir, string $wwwDir, string $handlerClass, array $quality, Nette\Http\Request $httpRequest)
    {
        $this->sourceDir = $sourceDir;
        $this->assetsDir = $assetsDir;
        $this->quality = $quality;
        $this->path = rtrim($httpRequest->url->basePath, '/') . str_replace($wwwDir, '', $this->assetsDir);
        $this->factory = new ImageFactory($handlerClass);
        $this->setLogger(new BarDumpLogger());
    }

    /**
     * @param ILogger $logger
     */
    public function setLogger(ILogger $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @return string
     */
    public function getAssetsDir(): string
    {
        return $this->assetsDir;
    }

    /**
     * @return string
     */
    public function getSourceDir(): string
    {
        return $this->sourceDir;
    }

    /**
     * @param mixed $image
     * @param mixed $size
     * @param string|int|null $flags
     * @param string|null $format
     * @param array|null $options
     * @return Result
     */
    public function request(mixed $image, mixed $size = null, string|int $flags = null, string $format = null, ?array $options = null): Result
    {
        $request = new Request($image, $size, $flags, $format, $options, false);
        $this->onBeforeRequest($request);
        $result = $this->process($request);
        $this->onAfterRequest($request, $result);
        return $result;
    }


    /**
     * @param mixed $image
     * @param mixed $size
     * @param string|int|null $flags
     * @param string|null $format
     * @param array|null $options
     * @return Result
     */
    public function requestStrict(mixed $image, mixed $size = null, string|int $flags = null, string $format = null, ?array $options = null): Result
    {
        $request = new Request($image, $size, $flags, $format, $options, true);
        $this->onBeforeRequest($request);
        $result = $this->process($request);
        $this->onAfterRequest($request, $result);
        return $result;
    }

    protected static function transformFlags(&$flags)
    {
        if (empty($flags)) {
            $flags = NImage::FIT;
        } elseif (!is_int($flags)) {
            $_flags = explode('_', strtolower($flags));
            $flags = 0;
            foreach ($_flags as $flag) {
                $flags |= self::FLAGS[$flag];
            }
            if (!isset($flags)) {
                throw new Nette\InvalidArgumentException('Mode is not allowed');
            }
        }
    }

    /**
     * @param Request $request
     * @return Result
     */
    protected function process(Request $request): Result
    {
        $image = $request->image;
        if (empty($image)) {
            return new Result('#');
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
            return new Result('#');
        }
        self::transformFlags($flags);

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
                    } else {
                        $this->logger->log($exception);
                    }
                }
            } elseif ($strictMode) {
                throw new FileNotFoundException("File '$originalFile' not found");
            } else {
                $this->logger->log("Image not found: $image $originalFile ");
            }
        };
        return new Result($this->getPath() . '/' . $thumbPath, $originalFile, $thumbnailFile, (@getimagesize($thumbnailFile)) ?: null, @mime_content_type($thumbnailFile) ?: null);
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
