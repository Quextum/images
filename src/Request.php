<?php declare(strict_types=1);

namespace Quextum\Images;

use Quextum\Images\Utils\SourceImage;

class Request
{
	/** @var \Stringable|string & mixed|SourceImage */
	public mixed $image;
	/** @var string|int[]|string[] */
	public mixed $size;
	public string|int|null $flags;
	public ?string $format;
	public ?array $options;
	public bool $strictMode;

	/** @param \Stringable|string & mixed|SourceImage $image */
	public function __construct(
		mixed             $image,
		string|array|int|null $size,
		int|string|null   $flags,
		?string           $format,
		?array            $options,
		bool              $strictMode
	)
	{
		$this->image = $image;
		$this->size = $size;
		$this->flags = $flags;
		$this->format = $format;
		$this->options = $options;
		$this->strictMode = $strictMode;
	}

}
