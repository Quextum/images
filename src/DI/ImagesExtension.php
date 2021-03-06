<?php declare(strict_types=1);

namespace Quextum\Images\DI;

use Imagick;
use Latte\Engine;
use Nette;
use Quextum\Images\Handlers\ImagickHandler;
use Quextum\Images\Handlers\NImageHandler;
use Quextum\Images\Storage;
use Quextum\Images\Macros\Latte;
use Quextum\Images\Pipes\ImagePipe;

/**
 * Class ImagesExtension
 * @package App\Images\DI
 */
class ImagesExtension extends Nette\DI\CompilerExtension
{
    private $pipe;

    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'quality' => Nette\Schema\Expect::arrayOf(Nette\Schema\Expect::int(), Nette\Schema\Expect::string())
                ->default(['default' => 90])->mergeDefaults(),
            'pipe' => Nette\Schema\Expect::string(ImagePipe::class),
            'storage' => Nette\Schema\Expect::string(Storage::class),
            'handler' => Nette\Schema\Expect::string(self::getDefaultHandler()),
            'sourceDir' => Nette\Schema\Expect::string()->assert('is_dir')->assert('is_readable'),
            'assetsDir' => Nette\Schema\Expect::string()->assert('is_dir')->assert('is_writable'),
            'macro' => Nette\Schema\Expect::string('img'),
        ]);
    }

    public static function getDefaultHandler(): string
    {
        return class_exists(Imagick::class) ? ImagickHandler::class : NImageHandler::class;
    }

    public function loadConfiguration(): void
    {
        $config = $this->getConfig();
        $builder = $this->getContainerBuilder();
        $this->pipe = $builder->addDefinition($this->prefix('imagePipe'))
            ->setFactory($config->pipe, [$config->assetsDir, $config->sourceDir, $this->getContainerBuilder()->parameters['wwwDir'], $config->handler, $config->quality])
            ->setType($config->pipe)->addTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT);
        $builder->addDefinition($this->prefix('imageStorage'))
            ->setFactory($config->storage, [$config->sourceDir])
            ->setType($config->storage)
            ->addTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT);
    }

    public function beforeCompile(): void
    {
        parent::beforeCompile();
        $config = $this->getConfig();
        if (class_exists(Engine::class)) {
            $latte = $this->getContainerBuilder()->getDefinition('latte.latteFactory')->getResultDefinition();
            $macro = Latte::class . '::install';
            $pipeName = "{$this->name}ImagePipe";
            $latte->addSetup('?->onCompile[] = function ($engine) { ' . $macro . '($engine->getCompiler(),?,?); }', ['@self', $pipeName, $config->macro]);
            $latte->addSetup('$service->addProvider(?,?)', [$pipeName, $this->pipe]);
        }
    }

}
