<?php declare(strict_types=1);

namespace Quextum\Images\Pipes;

use Exception;
use Quextum\Images\Request;
use Quextum\Images\Result;

/**
 * @method onBeforeRequest(Request $request)
 * @method onAfterRequest(Request $request, Result $result, Exception $exception)
 */
interface Provider
{

    /**@throws Exception */
    public function request(mixed $image, mixed $size = null, string|int $flags = null, string $format = null, ?array $options = null): Result;

    /**@throws Exception */
    public function requestStrict(mixed $image, mixed $size = null, string|int $flags = null, string $format = null, ?array $options = null): Result;

}
