<?php


namespace App\Images\Pipes;


use Nette\Caching\Cache;
use Nette\Caching\IStorage;

trait TCachedImagePipe
{

	/** @var Cache */
	private $cache;

	/**
	 * @param IStorage $storage
	 */
	public function injectStorage(IStorage $storage): void
	{
		$this->cache = new Cache($storage, __CLASS__);
	}

	/**
	 * @param string|null $image
	 * @param null $size
	 * @param null $flags
	 * @param string|null $format
	 * @param array|null $options
	 * @param bool $strictMode
	 * @return array
	 */
	protected function process(?string $image, $size = null, $flags = null, string $format = null, ?array $options = null, $strictMode = false): array
	{
		$args = array_values(get_defined_vars());
		return $this->cache->load(serialize($args), function (&$deps) use ($args) {
			[$path, $original, $thumb] = parent::process(...$args);
			if ($original) {
				$deps[Cache::FILES] = [$original];
			}
			return $path;
		});
	}

}
