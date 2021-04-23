<?php declare(strict_types=1);

namespace Quextum\Images\Pipes;

use Quextum\Images\FileNotFoundException;
use Quextum\Images\Result;

class FallbackImagePipe extends ImagePipe
{

    /**
     * {@inheritdoc}
     */
    public function request(mixed $image, mixed $size = null, string|int $flags = null, string $format = null, ?array $options = null): Result
    {
        try {
            return parent::requestStrict($image, $size, $flags, $format, $options);
        } catch (FileNotFoundException $exception) {
            [$namespace] = explode('/', $image) + ['default'];
            return parent::requestStrict("fallbacks/$namespace.jpg", $size, $flags, $format, $options);
        }
    }


}
