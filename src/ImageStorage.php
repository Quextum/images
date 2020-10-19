<?php

namespace Quextum\Images;

use Nette;
use SplFileInfo;
use RuntimeException;
use Nette\Utils\Finder;
use Nette\Utils\Random;
use Nette\Http\FileUpload;
use Nette\InvalidStateException;
use Nette\InvalidArgumentException;


/**
 * @author Filip Procházka <filip.prochazka@kdyby.org>
 * @method onUploadImage(string $path, string $namespace)
 */
class ImageStorage
{

    use Nette\SmartObject;

    /** @var array */
    public $onUploadImage = [];
    /** @var string */
    private $imagesDir;
    /** @var string */
    private $namespace = null;

    /**
     * @param string $dir
     */
    public function __construct($dir)
    {
        $this->setImagesDir($dir);
    }

    /**
     * @param $namespace
     * @return static
     */
    public function setNamespace($namespace): self
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @param string $namespace
     * @return bool
     */
    public function isNamespaceExists($namespace): bool
    {
        return file_exists($this->imagesDir . '/' . $namespace);
    }

    /**
     * @param FileUpload $file
     * @return Image
     * @throws InvalidArgumentException
     */
    public function upload(FileUpload $file): Image
    {
        if (!$file->isOk() || !$file->isImage()) {
            throw new InvalidArgumentException('');
        }

        $path = $this->generatePath($file->getSanitizedName());


        $file->move($path);
        $this->onUploadImage($path, $this->namespace);
        $this->namespace = null;

        return new Image($path);
    }


    public function generatePath(string $filename)
    {
        do {
            $name = Random::generate() . '.' . $filename;
        } while (file_exists($path = $this->getPath($name)));
        return $path;
    }

    public function getPath(string $name)
    {
        return $this->imagesDir . "/" . $this->namespace . "/" . $name;
    }

    /**
     * @param string $content
     * @param string $filename
     * @return Image
     */
    public function save($content, $filename)
    {
        $path = $this->generatePath($filename);
        Nette\Utils\FileSystem::write($path,$content,0777);
        return new Image($path);
    }

    /**
     * @param $filename
     * @throws InvalidStateException
     */
    public function deleteFile($filename)
    {
        if (empty($filename)) {
            throw new InvalidStateException('Filename was not provided');
        }
        /** @var $file SplFileInfo */
        foreach (Finder::findFiles($filename)->from($this->imagesDir . ($this->namespace ? "/" . $this->namespace : "")) as $file) {
            @unlink($file->getPathname());
        }
        $this->namespace = null;
    }

    /**
     * @return string
     */
    public function getImagesDir()
    {
        return $this->imagesDir;
    }

    /**
     * @param $dir
     */
    public function setImagesDir($dir): void
    {
        if (!is_dir($dir)) {
            umask(0);
            if (!mkdir($dir, 0777) && !is_dir($dir)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $dir));
            }
        }
        $this->imagesDir = $dir;
    }

}
