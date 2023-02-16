<?php declare(strict_types=1);

namespace Quextum\Images\DI;

use Latte;
use Nette;
use Quextum\Images\Handlers\ImagickHandler;
use Quextum\Images\Handlers\NImageHandler;
use Quextum\Images\Latte\ImagesExtension as ImagesLatteExtension;
use Quextum\Images\Latte\ImagesMacros;
use Quextum\Images\Middlewares\CachingMiddleware;
use Quextum\Images\Middlewares\Middleware;
use Quextum\Images\Pipes\Executor;
use Quextum\Images\Pipes\ImagePipe;
use Quextum\Images\Storage;

/**
 * Class ImagesExtension
 * @package App\Images\DI
 */
class ImagesExtension extends Nette\DI\CompilerExtension
{
    private Nette\DI\Definitions\ServiceDefinition $executor;

    public function getConfigSchema(): Nette\Schema\Schema
    {
        return Nette\Schema\Expect::structure([
            'quality' => Nette\Schema\Expect::arrayOf(Nette\Schema\Expect::int(), Nette\Schema\Expect::string())
                ->default(['default' => 90])->mergeDefaults(),
            'pipe' => Nette\Schema\Expect::string(ImagePipe::class),
            'middlewares' => Nette\Schema\Expect::arrayOf(Nette\Schema\Expect::anyOf(
                Nette\Schema\Expect::type(Middleware::class),
                Nette\Schema\Expect::type('callable'),
            ))->default([
                new Nette\DI\Definitions\Statement(CachingMiddleware::class)
            ]),
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
        $pipe = $builder->addDefinition($this->prefix('pipe'))
            ->setFactory($config->pipe, [$config->assetsDir, $config->sourceDir, $this->getContainerBuilder()->parameters['wwwDir'], $handler, $config->quality])
            ->setType($config->pipe)
            ->addTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT);

        $this->executor = $builder->addDefinition($this->prefix('executor'))
            ->setFactory(Executor::class, [$pipe, $config->middlewares]);

        $builder->addDefinition($this->prefix('storage'))
            ->setFactory($config->storage, [$config->sourceDir])
            ->setType($config->storage)
            ->addTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT);
    }

    public function beforeCompile(): void
    {
        parent::beforeCompile();
        if (class_exists(Latte\Engine::class)) {
            $builder = $this->getContainerBuilder();
            $config = $this->getConfig();
            $latte = $builder->getDefinition('latte.latteFactory')->getResultDefinition();
            if (class_exists(Latte\Extension::class)) {
                $latte->addSetup('$service->addExtension(?)', [
                    new Nette\DI\Definitions\Statement(ImagesLatteExtension::class, [
                        $this->executor, $config->macro
                    ])
                ]);
            } else {
                $macro = ImagesMacros::class . '::install';
                $pipeName = "{$this->name}ImagePipe";
                $latte->addSetup('?->onCompile[] = function ($engine) { ' . $macro . '($engine->getCompiler(),?,?); }', ['@self', $pipeName, $config->macro]);
                $latte->addSetup('$service->addProvider(?,?)', [$pipeName, $this->executor]);
            }
        }
    }

}
