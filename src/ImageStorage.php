<?php

namespace Quextum\Images;

use Nette;
use Nette\Http\FileUpload;
use Nette\InvalidArgumentException;
use Nette\InvalidStateException;
use Nette\Utils\Finder;
use Nette\Utils\Random;
use RuntimeException;
use SplFileInfo;


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

	/** @var string|null */
	private $namespace = null;

	/** @var string|null */
	private $originalPrefix = 'original';

	/**
	 * @param string $dir
	 */
	public function __construct($dir)
	{
		$this->setImagesDir($dir);
	}


	/**
	 * @param string|null $originalPrefix
	 */
	public function setOriginalPrefix(?string $originalPrefix): void
	{
		$this->originalPrefix = $originalPrefix;
	}


	/**
	 * @param $namespace
	 * @return static
	 */
	public function setNamespace($namespace): self
	{
		if ($namespace === null) {
			$this->namespace = null;
		} else {
			$this->namespace = $namespace . '/';
		}

		return $this;
	}


	/**
	 * @param string $namespace
	 * @return bool
	 */
	public function isNamespaceExists(string $namespace): bool
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

		do {
			$name = Random::generate() . '.' . $file->getSanitizedName();
		} while (file_exists($path = $this->imagesDir . '/' . $this->namespace . $this->originalPrefix . '/' . $name));

		$file->move($path);
		$this->onUploadImage($path, $this->namespace);
		$this->namespace = null;

		return new Image($path);
	}

	/**
	 * @param resource|string|array $content
	 * @param string $filename
	 * @return Image
	 */
	public function save($content, string $filename)
	{
		do {
			$name = Random::generate() . '.' . $filename;
		} while (file_exists($path = $this->imagesDir . "/" . $this->namespace . $this->originalPrefix . "/" . $name));

		@mkdir(dirname($path), 0777, true); // @ - dir may already exist
		file_put_contents($path, $content);

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

