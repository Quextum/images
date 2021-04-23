<?php

namespace Quextum\Images\Pipes;

use JsonException;
use Nette;
use Nette\FileNotFoundException;
use Nette\InvalidStateException;
use Nette\Utils\FileSystem;
use Nette\Utils\Image as NImage;
use Quextum\Images\Config;
use Quextum\Images\Handlers\IImageHandler;
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

    /** @var callable[] */
    public $onBeforeRequest;

    /** @var callable[] */
    public $onAfterRequest;

    /** @var callable[] */
    public $onBeforeSave;

    /** @var callable[] */
    public $onAfterSave;

    /** @var string */
    protected $assetsDir;

    /** @var string */
    protected $sourceDir;

    /** @var ImageFactory */
    protected $factory;

    /**  @var string */
    protected $path;

    /** @var ILogger */
    protected $logger;

    /** @var array */
    protected $quality;

    /**
     * @param string $assetsDir
     * @param string $sourceDir
     * @param string $wwwDir
     * @param string $handlerClass
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
     * @param null $size
     * @param null $flags
     * @param string|null $format
     * @param array|null $options
     * @return string
     * @throws JsonException
     */
    public function request($image, $size = null, $flags = null, string $format = null, ?array $options = null): Result
    {
        $request = new Request($image, $size, $flags, $format, $options, false);
        $this->onBeforeRequest($request);
        $result = $this->process($request);
        $this->onAfterRequest($request, $result);
        return $result;
    }


    /**
     * @param mixed $image
     * @param null $size
     * @param null $flags
     * @param string|null $format
     * @param array|null $options
     * @return string
     * @throws JsonException
     */
    public function requestStrict($image, $size = null, $flags = null, string $format = null, ?array $options = null): Result
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
     * @throws JsonException
     */
    protected function process(Request $request): Result
    {

        extract((array)$request);

        if (empty($image)) {
            return new Result('#');
        }
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
                $img->save($thumbnailFile,  $options['quality'] ?? $this->quality[$format] ?? $this->quality['default'],$format);
                $this->onAfterSave($thumbnailFile);
            } elseif ($strictMode) {
                throw new FileNotFoundException("File '$originalFile' not found");
            } else {
                $this->logger->log("Image not found: $image $originalFile ");
            }
        };
        return new Result($this->getPath() . '/' . $thumbPath, $originalFile, $thumbnailFile, getimagesize($thumbnailFile) ?: null, @mime_content_type($thumbnailFile) ?: null);
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
     * @param string|int[]|null $size
     * @return array|null[]|string[]
     */
    public static function parseSize($size): array
    {
        [$width, $height] = ((is_array($size) ? $size : explode('x', $size)) + [null, null]);
        return [$width ?: null, $height ?: null];
    }

    protected function getThumbnailPath(string $image, $width, $height, $options, $format, $flags, $hash)
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
