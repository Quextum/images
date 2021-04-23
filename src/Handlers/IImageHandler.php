<?php declare(strict_types=1);

namespace Quextum\Images\Handlers;


interface IImageHandler
{
	/**
	 * @param string $path
	 * @return static
	 */
	public static function create(string $path);

	public static function getSupportedFormats(): array;

	/**
	 * @param $width
	 * @param $height
	 * @param $flag
	 * @param array|null $options
	 * @return static
	 */
	public function resize($width, $height, $flag, $options = null);

	/**
	 * @param $x
	 * @param $y
	 * @param $width
	 * @param $height
	 * @return static
	 */
	public function crop($x, $y, $width, $height);


	/**
	 * @param string $path
	 * @param int $quality
	 * @param null $format
	 * @return static
	 */
	public function save(string $path, int $quality, $format = null);

	/** @return int */
	public function getWidth();

	/** @return int */
	public function getHeight();

}
