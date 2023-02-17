<?php declare(strict_types=1);

namespace Quextum\Images\Handlers;

/**
 * Class ImageFactory
 * @package Quextum\Images\Handlers
 * @template T of ImageHandler
 */
class StaticImageHandlerFactory implements ImageHandlerFactory
{

    /**
     * @phpstan-var class-string<T>
     * @var class-string<T>|ImageHandler
     */
    protected string $class;

    /**
     * ImageFactory constructor.
     * @phpstan-param class-string<T> $class
     */
    public function __construct(string $class)
    {
        $this->class = $class;
    }

    /** @throws ImageException */
    public function create(string $path): ImageHandler
    {
        return new ($this->class)($path);
    }

    public function getSupportedFormats(): array
    {
        return ($this->class)::getSupportedFormats();
    }

}
