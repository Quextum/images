<?php declare(strict_types=1);

namespace Quextum\Images\Middlewares;

use Nette\Caching\Cache;
use Nette\Caching\IStorage;
use Quextum\Images\Request;
use Quextum\Images\Result;

class CachingMiddleware implements Middleware
{

    private Cache $cache;

    public function __construct(IStorage $storage)
    {
        $this->cache = new Cache($storage, static::class);
    }

    public function __invoke(Request $request, callable $next): Result
    {
        return $this->cache->load(crc32(serialize($request)), function (&$deps) use ($request, $next) {
            $result = $next($request);
            if ($result->originalFile) {
                $deps[Cache::FILES] = $result->originalFile;
            }
            return $result;
        });
    }
}