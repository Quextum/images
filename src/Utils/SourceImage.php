<?php declare(strict_types=1);

namespace Quextum\Images\Utils;

class SourceImage
{
    public string $path;
    public int|null $width = null;
    public int|null $height = null;
    public string|null $mimeType;

    /**
     * @param string $path
     * @param int|null $width
     * @param int|null $height
     * @param string|null $mimeType
     */
    public function __construct(string $path, ?int $width = null, ?int $height = null, ?string $mimeType = null)
    {
        $this->path = $path;
        $this->width = $width;
        $this->height = $height;
        $this->mimeType = $mimeType;
    }


    public function __toString(): string
    {
        return $this->path;
    }


}