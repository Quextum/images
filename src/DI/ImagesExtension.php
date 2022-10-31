<?php declare(strict_types=1);

namespace Quextum\Images\DI;

use Nette;
use Quextum\Images\Handlers\ImagickHandler;
use Quextum\Images\Handlers\NImageHandler;
use Quextum\Images\Storage;
use Quextum\Images\Macros\Latte;
use Quextum\Images\Pipes\ImagePipe;
use Latte\Engine;

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
            'handler' => Nette\Schema\Expect::anyOf(
                Nette\Schema\Expect::string(),
                Nette\Schema\Expect::listOf(Nette\Schema\Expect::string())
            )->default([
                ImagickHandler::class,
                NImageHandler::class
            ]),
            'sourceDir' => Nette\Schema\Expect::string()->assert('is_dir')->assert('is_readable'),
            'assetsDir' => Nette\Schema\Expect::string()->assert('is_dir')->assert('is_writable'),
            'macro' => Nette\Schema\Expect::string('img'),
        ]);
    }

    public function loadConfiguration(): void
    {
        $config = $this->getConfig();
        $builder = $this->getContainerBuilder();
        $handler = null;
        foreach ((array)$config->handler as $handlerCandidate) {
            if ($handlerCandidate::isSupported()) {
                $handler = $handlerCandidate;
            }
        }
        $handler || throw new Nette\InvalidStateException("No valid handler specified in configuration");
        $this->pipe = $builder->addDefinition($this->prefix('pipe'))
            ->setFactory($config->pipe, [$config->assetsDir, $config->sourceDir, $this->getContainerBuilder()->parameters['wwwDir'], $handler, $config->quality])
            ->setType($config->pipe)->addTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT);
        $builder->addDefinition($this->prefix('storage'))
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
