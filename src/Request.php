<?php declare(strict_types=1);


namespace Quextum\Images;


class Request
{

	/** @var mixed */
	public $image;

	/** @var string|int[]|string[] */
	public $size;

	/** @var int|string */
	public $flags;

	/** @var string|null */
	public $format;

	/** @var array */
	public $options;

	/** @var bool */
	public $strictMode;

	/**
	 * Request constructor.
	 * @param mixed $image
	 * @param int|string $flags
	 * @param string|null $format
	 * @param array $options
	 * @param bool $strictMode
	 */
	public function __construct($image, $size, $flags, ?string $format, ?array $options, bool $strictMode)
	{
		$this->image = $image;
		$this->size = $size;
		$this->flags = $flags;
		$this->format = $format;
		$this->options = $options;
		$this->strictMode = $strictMode;
	}


}
