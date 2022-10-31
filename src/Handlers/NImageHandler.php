<?php

declare(strict_types=1);

namespace Quextum\Images\Handlers;

use JetBrains\PhpStorm\Pure;
use Nette\Utils\Image;
use function is_string;

class NImageHandler implements IImageHandler
{

    public const FORMATS = ['jpeg' => Image::JPEG, 'jpg' => Image::JPEG, 'png' => Image::PNG, 'gif' => Image::GIF, 'webp' => Image::WEBP];

    protected Image $image;

    /** @throws ImageException */
    public function __construct(string $file)
    {
        try {
            $this->image = Image::fromFile($file);
        } catch (\Exception $exception) {
            throw new ImageException(previous: $exception);
        }
    }

    /** @throws ImageException */
    public static function create(string $path): static
    {
        return new self($path);
    }

    public static function getSupportedFormats(): array
    {
        return ['jpeg', 'png', 'gif', 'webp'];
    }

    /** @throws ImageException */
    public function save(string $path = null, int $quality = null, $format = null): static
    {
        try {
            if (is_string($format)) {
                $format = self::FORMATS[$format];
            }
            $this->image->save($path, $quality, $format);
        } catch (\Exception $exception) {
            throw new ImageException(previous: $exception);
        }
        return $this;
    }


    /** @throws ImageException */
    public function resize($width, $height, $flag, array $options = null): static
    {
        try {
            $this->image->resize($width, $height, $flag);
        } catch (\Exception $exception) {
            throw new ImageException(previous: $exception);
        }
        return $this;
    }


    /** @throws ImageException */
    public function crop($x, $y, $width, $height): static
    {
        try {
            $this->image->crop($x, $y, $width, $height);
        } catch (\Exception $exception) {
            throw new ImageException(previous: $exception);
        }
        return $this;
    }

    #[Pure] public function getWidth(): int
    {
        return $this->image->getWidth();
    }

    #[Pure] public function getHeight(): int
    {
        return $this->image->getHeight();
    }

    public static function isSupported(): bool
    {
        return extension_loaded('gd');
    }
}
