<?php declare(strict_types=1);

namespace Quextum\Images\Handlers;


interface ImageHandler
{

    public static function getSupportedFormats(): array;

    public static function isSupported(): bool;

    public function resize($width, $height, $flag, array $options = null): static;

    public function crop($x, $y, $width, $height): static;

    public function save(string $path, int $quality, $format = null): static;

    public function getWidth(): int;

    public function getHeight(): int;

}
