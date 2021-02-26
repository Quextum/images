<?php


namespace Quextum\Images;


use Nette\SmartObject;

class Result
{

    use SmartObject;

    public string $src;
    public ?string $originalFile;
    public ?string $thumbnailFile;
    public ?array $size;
    public ?string $mime;
    /**
     * Result constructor.
     * @param string $src
     * @param string|null $originalFile
     * @param string|null $thumbnailFile
     * @param int $width
     * @param int $height
     */
    public function __construct(string $src, ?string $originalFile = null, ?string $thumbnailFile = null, ?array $size = null,?string $mime)
    {
        $this->src = $src;
        $this->originalFile = $originalFile;
        $this->thumbnailFile = $thumbnailFile;
        $this->size = $size;
        $this->mime = $mime;
    }

    public function __toString(): string
    {
        return $this->src;
    }
}