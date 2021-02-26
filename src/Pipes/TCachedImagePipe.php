<?php


namespace Quextum\Images\Pipes;


use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Quextum\Images\Request;
use Quextum\Images\Result;

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
    protected function process(Request $request): Result
    {
        return $this->cache->load(crc32(serialize($request)), function (&$deps) use ($request): Result {
            $result = parent::process($request);
            if ($result->originalFile) $deps[Cache::FILES] = $result->originalFile;
            return $result;
        });
    }

}
