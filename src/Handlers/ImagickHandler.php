<?php declare(strict_types=1);

namespace Quextum\Images\Handlers;

use Imagick;
use Nette\Utils\Image;

class ImagickHandler implements IImageHandler
{

	public const QUALITY = 100;

	public const DEFAULT_OPTIONS = [
		'center' => ['50%', '50%']
	];

	/**
	 * @var Imagick
	 */
	private $image;

	/**
	 * GPHandler constructor.
	 * @param string $path
	 */
	public function __construct(string $path)
	{
		$this->image = new Imagick($path);
	}

	/**
	 * @param string $path
	 * @return ImagickHandler
	 */
	public static function create(string $path): self
	{
		return new static($path);
	}

	public static function getSupportedFormats(): array
	{
		return array_map('\strtolower', Imagick::queryFormats('*'));
	}

	public function resize($width, $height, $flags, $options = null): self
	{
		if ($flags & Image::EXACT) {
			[$x, $y] = array_merge(self::DEFAULT_OPTIONS, $options ?: [])['center'];
			return $this->resize($width, $height, Image::FILL)->crop($x, $y, $width, $height);
		}
		[$newWidth, $newHeight] = Image::calculateSize($this->getWidth(), $this->getHeight(), $width, $height, $flags);
		$this->image->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1, !($flags & Image::SHRINK_ONLY));
		return $this;
	}

	public function crop($x, $y, $width, $height): self
	{
		[$x, $y, $width, $height] = Image::calculateCutout($this->getWidth(), $this->getHeight(), $x, $y, $width, $height);
		$this->image->cropImage($width ? (int)$width : null, $height ? (int)$height : null, $x, $y);
		return $this;
	}

	public function getWidth(): int
	{
		return $this->image->getImageWidth();
	}

	public function getHeight(): int
	{
		return $this->image->getImageHeight();
	}

	public function save(string $path, int $quality = self::QUALITY, $format = null): self
	{
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
		return $this;
	}

	public function backgroundMasking(): void
	{
		# replace white background with fuchsia
		$this->image->floodFillPaintImage('rgb(255, 0, 255)', 2500, 'rgb(255,255,255)', 0, 0, false);

		$this->image->transparentPaintImage('rgb(255,0,255)', 0, 10, false);
	}

	public function trimImage(float $fuzz): void
	{
		$this->image->trimImage($fuzz);
		$this->image->setImagePage(0, 0, 0, 0);
	}

	/**
	 * @return Imagick
	 */
	public function getImage(): Imagick
	{
		return $this->image;
	}
}
