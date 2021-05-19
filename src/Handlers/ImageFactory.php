<?php declare(strict_types=1);

namespace Quextum\Images\Handlers;

/**
 * Class ImageFactory
 * @package Quextum\Images\Handlers
 * @template T of IImageHandler
 */
class ImageFactory
{

    /**
     * @phpstan-var class-string<T>
     * @var class-string<T>|IImageHandler
     */
	protected string $class;

	/**
	 * ImageFactory constructor.
	 * @param string $class
     * @phpstan-param class-string<T> $class
	 */
	public function __construct(string $class)
	{
		$this->class = $class;
	}

	/**
	 * @param string $path
	 * @return IImageHandler
     * @throws ImageException
	 */
	public function create(string $path): IImageHandler
	{
		return ($this->class)::create($path);
	}

	public function getSupportedFormats(): array
    {
		return ($this->class)::getSupportedFormats();
	}
}
