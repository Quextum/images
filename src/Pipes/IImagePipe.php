<?php declare(strict_types=1);

namespace Quextum\Images\Pipes;

use Quextum\Images\Result;

interface IImagePipe
{
    public function request(mixed $image, mixed $size = null, string|int $flags = null, string $format = null, ?array $options = null): Result;
}