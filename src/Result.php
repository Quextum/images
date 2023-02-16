<?php


namespace Quextum\Images;


use JetBrains\PhpStorm\Pure;
use Nette\SmartObject;

class Result implements \Stringable
{

    use SmartObject;

    public string $src;
    public ?string $originalFile;
    public ?string $thumbnailFile;
    public ?array $size;
    public ?string $mime;
    public bool $ready = true;

    /**
     * Result constructor.
     * @param string $src
     * @param string|null $originalFile
     * @param string|null $thumbnailFile
     * @param array|null $size
     * @param string|null $mime
     */
    public function __construct(string $src, ?string $originalFile = null, ?string $thumbnailFile = null, ?array $size = null, ?string $mime = null)
    {
        $this->src = $src;
        $this->originalFile = $originalFile;
        $this->thumbnailFile = $thumbnailFile;
        $this->size = $size;
        $this->mime = $mime;
    }

    #[Pure] public static function from(string $src, ?string $originalFile = null, ?string $thumbnailFile = null, ?array $size = null, ?string $mime = null): static
    {
        return new static($src, $originalFile, $thumbnailFile, $size, $mime);
    }

    public function __toString(): string
    {
        return $this->src;
    }

    public function setReady(bool $ready): static
    {
        $this->ready = $ready;
        return $this;
    }

    /**
     * @param string $src
     * @return Result
     */
    public function setSrc(string $src): Result
    {
        $this->src = $src;
        return $this;
    }

    /**
     * @param string|null $originalFile
     * @return Result
     */
    public function setOriginalFile(?string $originalFile): Result
    {
        $this->originalFile = $originalFile;
        return $this;
    }

    /**
     * @param string|null $thumbnailFile
     * @return Result
     */
    public function setThumbnailFile(?string $thumbnailFile): Result
    {
        $this->thumbnailFile = $thumbnailFile;
        return $this;
    }

    /**
     * @param array|null $size
     * @return Result
     */
    public function setSize(?array $size): Result
    {
        $this->size = $size;
        return $this;
    }

    /**
     * @param string|null $mime
     * @return Result
     */
    public function setMime(?string $mime): Result
    {
        $this->mime = $mime;
        return $this;
    }

}