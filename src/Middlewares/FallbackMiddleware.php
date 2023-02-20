<?php declare(strict_types=1);

namespace Quextum\Images\Middlewares;

use Quextum\Images\FileNotFoundException;
use Quextum\Images\Request;
use Quextum\Images\Result;

class FallbackMiddleware implements Middleware
{

	/** @var Closure(Request|null): string */
	private $callback;

	public function __construct(callable $callback = null)
	{
		$this->callback = $callback ?: [$this, 'getFallbackImage'];
	}

	public function getFallbackImage(Request|null $request): string
	{
		$image= $request?->image;
		$namespace = 'default';
		if (is_string($image) && count($parts = explode('/', $image, 1)) > 1) {
			$namespace = $parts[0];
		}
		if (is_array($image) && isset($image['namespace'])) {
			$namespace = $image['namespace'];
		}
		if (is_object($image) && isset($image->namespace)) {
			$namespace = $image->namespace;
		}
		return "fallbacks/$namespace.jpg";
	}


	public function __invoke(Request $request, callable $next): Result
	{
		try {
			$request->strictMode = true;
			return $next($request);
		} catch (FileNotFoundException $exception) {
			try {
				$request->image = ($this->callback)($request);
				return $next($request);
			} catch (FileNotFoundException $exception) {
				$request->image = ($this->callback)(null);
				$request->strictMode = false;
				return $next($request);
			}
		}
	}
}
