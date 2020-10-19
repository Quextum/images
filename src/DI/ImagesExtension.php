<?php declare(strict_types=1);

namespace Quextum\Images\DI;

use Quextum\Images\Handlers\ImagickHandler;
use Quextum\Images\Handlers\NImageHandler;
use Quextum\Images\ImageStorage;
use Quextum\Images\Macros\Latte;
use Quextum\Images\Pipes\ImagePipe;
use Imagick;
use Nette;

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
            'pipe' => Nette\Schema\Expect::string(ImagePipe::class),
            'handler' => Nette\Schema\Expect::string(self::getDefaultHandler()),
            'sourceDir' => Nette\Schema\Expect::string()->assert('is_dir')->assert('is_readable'),
            'assetsDir' => Nette\Schema\Expect::string()->assert('is_dir')->assert('is_writable'),
        ])->castTo('array');
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
            ->setFactory($config['pipe'], [$config['assetsDir'], $config['sourceDir'], $this->getContainerBuilder()->parameters['wwwDir'], $config['handler']])
            ->setType(ImagePipe::class);
        $builder->addDefinition($this->prefix('imageStorage'))->setFactory(ImageStorage::class, [$config['sourceDir']]);
    }

    public function beforeCompile(): void
    {
        parent::beforeCompile();
        if (class_exists(Latte::class)) {
            $latte = $this->getContainerBuilder()->getDefinition('latte.latteFactory')->getResultDefinition();
            $macro = Latte::class . '::install';
            $latte->addSetup('?->onCompile[] = function ($engine) { ' . $macro . '($engine->getCompiler()); }', ['@self']);
            $latte->addSetup('$service->addProvider(?,?)', ['imagePipe', $this->pipe]);
        }
    }

}
