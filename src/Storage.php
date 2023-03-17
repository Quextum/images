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
 * @method onUploadImage(string $path, string $namespace)
 */
class Storage
{

	use Nette\SmartObject;

	public array $onUploadImage = [];
	private string $imagesDir;
	private string|null $namespace = null;

	public function __construct(string $dir)
	{
		$this->setImagesDir($dir);
	}

	public function setNamespace(?string $namespace): static
	{
		$this->namespace = $namespace;
		return $this;
	}

	public function isNamespaceExists(string $namespace): bool
	{
		return file_exists($this->imagesDir . '/' . $namespace);
	}

	/**  @throws InvalidArgumentException */
	public function upload(FileUpload $file): Image
	{
		if (!$file->isOk()) {
			throw new InvalidArgumentException('File is not OK!');
		}
		$path = $this->generatePath($file->getSanitizedName());
		$file->move($path);
		$this->onUploadImage($path, $this->namespace);
		$this->namespace = null;
		return new Image($path);
	}

	public function generatePath(string $filename): string
	{
		do {
			$name = Random::generate() . '.' . $filename;
		} while (file_exists($path = $this->getPath($name)));
		return $path;
	}

	public function getPath(string $name): string
	{
		return $this->imagesDir . "/" . $this->namespace . "/" . $name;
	}

	public function save(string $content, string $filename): Image
	{
		$path = $this->generatePath($filename);
		Nette\Utils\FileSystem::write($path, $content, 0777);
		return new Image($path);
	}

	/**  @throws InvalidStateException */
	public function deleteFile($filename): void
	{
		if (empty($filename)) {
			throw new InvalidStateException('Filename was not provided');
		}
		/** @var $file SplFileInfo */
		foreach (Finder::findFiles($filename)->from(rtrim("$this->imagesDir/$this->namespace", '/')) as $file) {
			@unlink($file->getPathname());
		}
		$this->namespace = null;
	}

	public function getImagesDir(): string
	{
		return $this->imagesDir;
	}

	public function setImagesDir(string $dir): void
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
