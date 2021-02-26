<?php

declare(strict_types=1);

namespace Quextum\Images\Handlers;

use Nette\Utils\Image;
use function is_string;

class NImageHandler implements IImageHandler
{


	public const FORMATS = ['jpeg' => Image::JPEG, 'jpg' => Image::JPEG, 'png' => Image::PNG, 'gif' => Image::GIF, 'webp' => Image::WEBP];

	/** @var  Image */
	protected $image;

	/**
	 * NImageHandler constructor.
	 * @param string $file
	 */
	public function __construct(string $file)
	{
		$this->image = Image::fromFile($file);
	}

	public static function create(string $path)
	{
		return new self($path);
	}

	public static function getSupportedFormats(): array
	{

		return ['jpeg', 'png', 'gif', 'webp'];
	}

	public function save(string $file = null, int $quality = null, $type = null)
	{
		if (is_string($type)) {
			$type = self::FORMATS[$type];
		}
		return $this->image->save($file, $quality, $type);
	}

	/**
	 * @param $width
	 * @param $height
	 * @param $flag
	 * @param null $options
	 * @return static
	 */
	public function resize($width, $height, $flag, $options = null)
	{
		$this->image->resize($width, $height, $flag);
		return $this;
	}

	/**
	 * @param $x
	 * @param $y
	 * @param $width
	 * @param $height
	 * @return static
	 */
	public function crop($x, $y, $width, $height)
	{
		$this->image->crop($x, $y, $width, $height);
		return $this;
	}

	/** @return int */
	public function getWidth()
	{
		return $this->image->getWidth();

	}

	/** @return int */
	public function getHeight()
	{
		return $this->image->getHeight();
	}
}
