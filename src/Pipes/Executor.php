<?php declare(strict_types=1);

namespace Quextum\Images\Pipes;

use Closure;
use Exception;
use Nette\SmartObject;
use Quextum\Images\Request;
use Quextum\Images\Result;

/**
 * @method onBeforeRequest(Request $request)
 * @method onAfterRequest(Request $request, Result $result, Exception $exception)
 */
class Executor
{

    use SmartObject;

    public array $onBeforeRequest;
    public array $onAfterRequest;


    public function __construct(
        private  IImagePipe $pipe,
        private  array      $middlewares
    )
    {
    }

    /**@throws Exception */
    public function request(mixed $image, mixed $size = null, string|int $flags = null, string $format = null, ?array $options = null): Result
    {
        return $this->process(new Request($image, $size, $flags, $format, $options, false));
    }

    /**@throws Exception */
    public function requestStrict(mixed $image, mixed $size = null, string|int $flags = null, string $format = null, ?array $options = null): Result
    {
        return $this->process(new Request($image, $size, $flags, $format, $options, true));
    }

    /**@throws Exception */
    private function process(Request $request): Result
    {
        $result = null;
        $error = null;
        try {
            $this->onBeforeRequest($request);
            $action = Closure::fromCallable([$this->pipe, 'process']);
            foreach ($this->middlewares as $middleware) {
                $action = static fn(Request $request): Result => $middleware($request, $action);
            }
            $result = $action($request);
        } catch (Exception $exception) {
            $error = $exception;
        } finally {
            $this->onAfterRequest($request, $result, $error);
        }
        if ($error) {
            throw $error;
        }
        return $result;
    }

}