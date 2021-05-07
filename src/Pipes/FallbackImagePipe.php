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
            $image = $this->getFallbackImage($image);
            try {
                return parent::requestStrict($image, $size, $flags, $format, $options);
            } catch (FileNotFoundException $exception) {
                $image = $this->getFallbackImage(null);
                return parent::requestStrict($image, $size, $flags, $format, $options);
            }
        }
    }


    public function getFallbackImage(mixed $image)
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


}
