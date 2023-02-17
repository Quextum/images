<?php declare(strict_types=1);

namespace Quextum\Images\Handlers;

use Imagick;
use Nette\Utils\Image;

final class ImagickHandler implements ImageHandler
{

    public const QUALITY = 100;

    public const DEFAULT_OPTIONS = [
        'center' => ['50%', '50%']
    ];

    private Imagick $image;

    /** @throws ImageException */
    public function __construct(string $path)
    {
        try {
            $this->image = new Imagick($path);
        } catch (\ImagickException $e) {
            throw new ImageException('Unable to create image', previous: $e);
        }
    }

    public static function getSupportedFormats(): array
    {
        return array_map('\strtolower', Imagick::queryFormats('*'));
    }

    /**  @throws ImageException */
    public function resize($width, $height, $flag, array $options = null): static
    {
        try {
            if ($flag & Image::EXACT) {
                [$x, $y] = array_merge(self::DEFAULT_OPTIONS, $options ?: [])['center'];
                return $this->resize($width, $height, Image::FILL)->crop($x, $y, $width, $height);
            }
            [$newWidth, $newHeight] = Image::calculateSize($this->getWidth(), $this->getHeight(), $width, $height, $flag);
            $this->image->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1, !($flag & Image::SHRINK_ONLY));
        } catch (\ImagickException $e) {
            throw new ImageException(previous: $e);
        }
        return $this;
    }

    /**  @throws ImageException */
    public function crop($x, $y, $width, $height): static
    {
        try {
            [$x, $y, $width, $height] = Image::calculateCutout($this->getWidth(), $this->getHeight(), $x, $y, $width, $height);
            $this->image->cropImage($width ? (int)$width : null, $height ? (int)$height : null, $x, $y);
        } catch (\ImagickException $e) {
            throw new ImageException('Unable to crop image', previous: $e);
        }
        return $this;
    }

    /**  @throws ImageException */
    public function getWidth(): int
    {
        try {
            return $this->image->getImageWidth();
        } catch (\ImagickException $e) {
            throw new ImageException(previous: $e);
        }
    }

    /**  @throws ImageException */
    public function getHeight(): int
    {
        try {
            return $this->image->getImageHeight();
        } catch (\ImagickException $e) {
            throw new ImageException(previous: $e);
        }
    }

    /**  @throws ImageException */
    public function save(string $path, int $quality = self::QUALITY, $format = null): static
    {
        try {
            if ($format) {
                $this->image->setImageFormat($format);
                //$this->image->setImageCompression($format);
            }
            $this->image->setColorspace(Imagick::COLORSPACE_RGB);
            $this->image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $this->image->setSamplingFactors(['2x2', '1x1', '1x1']);
            $this->image->setImageChannelDepth(Imagick::CHANNEL_ALL, 8);
            $this->image->setImageCompressionQuality($quality);
            //$this->image->setCompression(\Imagick::COMPRESSION_);
            $this->image->stripImage();
            $this->image->writeImage($path);
        } catch (\ImagickException $e) {
            throw new ImageException('Unable to save image', previous: $e);
        }
        return $this;
    }

    /**  @throws ImageException */
    public function backgroundMasking(): static
    {
        try {
            # replace white background with fuchsia
            $this->image->floodFillPaintImage('rgb(255, 0, 255)', 2500, 'rgb(255,255,255)', 0, 0, false);
            $this->image->transparentPaintImage('rgb(255,0,255)', 0, 10, false);
        } catch (\ImagickException $e) {
            throw new ImageException(previous: $e);
        }
        return $this;
    }

    /**  @throws ImageException */
    public function trimImage(float $fuzz): static
    {
        try {
            $this->image->trimImage($fuzz);
            $this->image->setImagePage(0, 0, 0, 0);
        } catch (\ImagickException $e) {
            throw new ImageException(previous: $e);
        }
        return $this;
    }


    public function getImage(): Imagick
    {
        return $this->image;
    }

    public static function isSupported(): bool
    {
        return
            extension_loaded('imagick') &&
            class_exists(Imagick::class);
    }
}
