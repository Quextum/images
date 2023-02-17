<?php declare(strict_types=1);

namespace Quextum\Images\Handlers;

use Jcupitt\Vips;
use Nette\Utils\Image;

class VIPSHandler implements ImageHandler
{
    public const QUALITY = 100;

    public const DEFAULT_OPTIONS = [
        'center' => ['50%', '50%']
    ];

    private Vips\Image $image;

    /**
     * GPHandler constructor.
     * @param string $path
     * @throws ImageException
     */
    public function __construct(string $path)
    {
        try {
            $this->image = Vips\Image::newFromFile($path, ['access' => 'sequential']);
        } catch (Vips\Exception $e) {
            throw new ImageException('Unable to create image', previous: $e);
        }
    }

    public static function getSupportedFormats(): array
    {
        return [];
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
            $this->image = $this->image->thumbnail_image($newWidth, ['height' => $newHeight]);
        } catch (Vips\Exception $e) {
            throw new ImageException(previous: $e);
        }
        return $this;
    }

    /**  @throws ImageException */
    public function crop($x, $y, $width, $height): static
    {
        try {
            [$x, $y, $width, $height] = Image::calculateCutout($this->getWidth(), $this->getHeight(), $x, $y, $width, $height);
            $this->image = $this->image->crop($x, $y, $width ? (int)$width : null, $height ? (int)$height : null);
        } catch (Vips\Exception $e) {
            throw new ImageException('Unable to crop image', previous: $e);
        }
        return $this;
    }

    public function getWidth(): int
    {
        return $this->image->width;
    }

    public function getHeight(): int
    {
        return $this->image->height;
    }

    /**
     * @throws ImageException
     * @see Vips\BandFormat
     */
    public function save(string $path, int $quality = self::QUALITY, $format = null): static
    {
        try {
            $this->image->writeToFile($path, ['Q' => $quality, 'strip' => true]);
        } catch (Vips\Exception $e) {
            throw new ImageException('Unable to save image', previous: $e);
        }
        return $this;
    }

    public function getImage(): Vips\Image
    {
        return $this->image;
    }

    public static function isSupported(): bool
    {
        return
            extension_loaded('vips') &&
            extension_loaded('FFI') &&
            class_exists(Vips\Image::class) &&
            self::test();
    }

    public static function test(): bool
    {
        try {
            $data = Vips\Image::newFromArray([[1]])->writeToBuffer(".jpg");
            return (bool)$data;
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
