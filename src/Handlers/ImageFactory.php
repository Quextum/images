<?php declare(strict_types=1);

namespace Quextum\Images\Handlers;


class ImageFactory
{

	/** @var  string|IImageHandler */
	protected $class;

	/**
	 * ImageFactory constructor.
	 * @param string $class
	 */
	public function __construct(string $class)
	{
		$this->class = $class;
	}


	/**
	 * @param string $path
	 * @return IImageHandler
	 */
	public function create(string $path): IImageHandler
	{
		return ($this->class)::create($path);
	}

	public function getSupportedFormats()
	{
		return ($this->class)::getSupportedFormats();
	}
}
