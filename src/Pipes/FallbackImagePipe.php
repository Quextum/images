<?php


namespace Quextum\Images\Pipes;


use JsonException;
use Nette\FileNotFoundException;

class FallbackImagePipe extends ImagePipe
{

	/**
	 * @param mixed $image
	 * @param null $size
	 * @param null $flags
	 * @param string|null $format
	 * @param array|null $options
	 * @return string
	 * @throws JsonException
	 */
	public function request($image, $size = null, $flags = null, string $format = null, array $options = null): string
	{
		try {
			return parent::requestStrict($image, $size, $flags, $format, $options);
		} catch (FileNotFoundException $exception) {
			[$namespace] = explode('/', $image);
			return parent::requestStrict("fallbacks/$namespace.jpg", $size, $flags, $format, $options);
		}
	}


}
