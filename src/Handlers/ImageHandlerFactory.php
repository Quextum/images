<?php declare(strict_types=1);

namespace Quextum\Images\Handlers;


interface ImageHandlerFactory
{
    /**  @throws ImageException */
    public function create(string $path): ImageHandler;

    public function getSupportedFormats(): array;

}
