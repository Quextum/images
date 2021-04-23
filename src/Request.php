<?php declare(strict_types=1);


namespace Quextum\Images;


class Request
{
	public mixed $image;
	/** @var string|int[]|string[] */
	public mixed $size;
	public string|int|null $flags;
	public ?string $format;
	public ?array $options;
	public bool $strictMode;

    /**
     * Request constructor.
     * @param mixed $image
     * @param mixed $size
     * @param int|string|null $flags
     * @param string|null $format
     * @param array|null $options
     * @param bool $strictMode
     */
	public function __construct(mixed $image,mixed $size,int|string|null $flags, ?string $format, ?array $options, bool $strictMode)
	{
		$this->image = $image;
		$this->size = $size;
		$this->flags = $flags;
		$this->format = $format;
		$this->options = $options;
		$this->strictMode = $strictMode;
	}


}
