<?php declare(strict_types=1);

namespace Quextum\Images\Middlewares;

use Quextum\Images\FileNotFoundException;
use Quextum\Images\Request;
use Quextum\Images\Result;

class FallbackMiddleware implements Middleware
{

    public function getFallbackImage(mixed $image): string
    {
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
            return $next($request);
        } catch (FileNotFoundException $exception) {
            $image = $this->getFallbackImage($request->image);
            try {
                $request->image = $image;
                return $next($request);
            } catch (FileNotFoundException $exception) {
                $image = $this->getFallbackImage(null);
                $request->image = $image;
                return $next($request);
            }
        }
    }
}
