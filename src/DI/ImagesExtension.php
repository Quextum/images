<?php declare(strict_types=1);

namespace Quextum\Images\DI;

use Latte;
use Nette;
use Nette\Schema\Elements\Type;
use Quextum\Images\Handlers\ImageHandler;
use Quextum\Images\Handlers\ImageHandlerFactory;
use Quextum\Images\Handlers\ImagickHandler;
use Quextum\Images\Handlers\NetteImageHandler;
use Quextum\Images\Handlers\StaticImageHandlerFactory;
use Quextum\Images\Handlers\VIPSHandler;
use Quextum\Images\Latte\ImagesExtension as ImagesLatteExtension;
use Quextum\Images\Latte\ImagesMacros;
use Quextum\Images\Middlewares\CachingMiddleware;
use Quextum\Images\Middlewares\Middleware;
use Quextum\Images\Pipes\BlockingImagePipe;
use Quextum\Images\Pipes\ImagePipe;
use Quextum\Images\Pipes\Provider;
use Quextum\Images\Pipes\ProviderMiddlewares;
use Quextum\Images\Storage;
use Quextum\Images\Utils\BarDumpLogger;
use Quextum\Images\Utils\DummyLogger;
use ReflectionClass;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Class ImagesExtension
 * @package App\Images\DI
 */
class ImagesExtension extends Nette\DI\CompilerExtension
{
	private Nette\DI\Definitions\ServiceDefinition $provider;

	public function getConfigSchema(): Nette\Schema\Schema
	{
		$imageHandlerType = Nette\Schema\Expect::anyOf(
			self::interface(ImageHandlerFactory::class),
			self::interface(ImageHandler::class),
			Nette\Schema\Expect::type(Nette\DI\Definitions\Statement::class)
		);
		return Nette\Schema\Expect::structure(array_filter([
			'quality' => Nette\Schema\Expect::arrayOf(Nette\Schema\Expect::int(), Nette\Schema\Expect::string())
				->default(['default' => 90])->mergeDefaults(),
			'pipe' => self::interfaceStatement(ImagePipe::class)
				->default(BlockingImagePipe::class),
			'provider' => self::interfaceStatement(Provider::class)
				->default(ProviderMiddlewares::class),
			'middlewares' => Nette\Schema\Expect::arrayOf(
				Nette\Schema\Expect::anyOf(
					Nette\Schema\Expect::type(Nette\DI\Definitions\Statement::class),
					self::interface(Middleware::class)
						->assert(function (&$value) {
							return $value = new Nette\DI\Definitions\Statement($value);
						}),
					Nette\Schema\Expect::type('callable')
						->assert('is_callable'),
				))
				->default([
					new Nette\DI\Definitions\Statement(CachingMiddleware::class)
				]),
			'logger' => self::interfaceStatement(ILogger::class)
				->default(class_exists(Debugger::class) ? BarDumpLogger::class : DummyLogger::class),
			'storage' => Nette\Schema\Expect::string(Storage::class),
			'handler' => Nette\Schema\Expect::listOf($imageHandlerType)
				->default([
					NetteImageHandler::class,
					ImagickHandler::class,
					VIPSHandler::class,
				])->castTo('array')
				->mergeDefaults(),
			'sourceDir' => Nette\Schema\Expect::string()->assert('is_dir')->assert('is_readable'),
			'assetsDir' => Nette\Schema\Expect::string()->assert('is_dir')->assert('is_writable'),
			'macro' => Nette\Schema\Expect::string('img'),
			'filter' => Nette\Schema\Expect::anyOf(Nette\Schema\Expect::string(), Nette\Schema\Expect::bool())->default(true),
			'function' => Nette\Schema\Expect::anyOf(Nette\Schema\Expect::string(), Nette\Schema\Expect::bool())->default(true),
			'tags' => class_exists(Latte\Extension::class) ? Nette\Schema\Expect::arrayOf(
				Nette\Schema\Expect::listOf(Nette\Schema\Expect::string())
			)->default(ImagesLatteExtension::TAGS)
				->mergeDefaults(): false,
		]));
	}

	public static function interface(string $interface): Type
	{
		return Nette\Schema\Expect::string()
			->assert(self::assertInterface($interface), "Class must implement interface $interface");
	}

	public static function interfaceStatement(string $interface): Nette\Schema\Elements\AnyOf
	{
		$assert = self::assertInterface($interface);
		return Nette\Schema\Expect::anyOf(
			Nette\Schema\Expect::type(Nette\DI\Definitions\Statement::class)
				->assert(function (Nette\DI\Definitions\Statement $statement) use ($assert) {
					return $assert($statement->entity);
				}, "Class must implement interface $interface"),
			self::interface($interface),
		);
	}

	public static function assertInterface(string $interface): callable
	{
		return static function (string $class) use ($interface) {
			return class_exists($class) && (new ReflectionClass($class))->implementsInterface($interface);
		};
	}

	public static function assertHandler(): callable
	{
		return static function (/** @var class-string<ImageHandler>|Nette\DI\Definitions\Definition $handlerCandidate */ $handlerCandidate) {
			/** @var class-string<ImageHandler> $class */
			$class = $handlerCandidate;
			if ($handlerCandidate instanceof Nette\DI\Definitions\Statement) {
				$class = $handlerCandidate->getEntity();
				if ($class instanceof Nette\DI\Definitions\Definition) {
					$class = $class->getType();
				} else if ($class instanceof Nette\DI\Definitions\Reference) {
					$class = $class->getValue();
				}
			}
			return $class::isSupported();
		};
	}


	public function loadConfiguration(): void
	{
		$config = $this->getConfig();
		$builder = $this->getContainerBuilder();
		$supported = false;
		$handlerStatement = null;
		foreach (array_reverse($config->handler) as $handlerCandidate) {
			if ($handlerCandidate instanceof Nette\DI\Definitions\Statement) {
				$class = $handlerCandidate->getEntity();
				$handlerStatement = $handlerCandidate;
				if ($class instanceof Nette\DI\Definitions\Definition) {
					$class = $class->getType();
				} else if ($class instanceof Nette\DI\Definitions\Reference) {
					$class = $class->getValue();
				}
			} else {
				$class = $handlerCandidate;
			}
			$rf = new ReflectionClass($class);
			if ($rf->implementsInterface(ImageHandlerFactory::class)) {
				$handlerStatement = $handlerStatement ?? new Nette\DI\Definitions\Statement($class);
				$supported = true;
				break;
			}
			if ($rf->implementsInterface(ImageHandler::class)) {
				$handlerStatement = new Nette\DI\Definitions\Statement(StaticImageHandlerFactory::class, [$class]);
				if ($class::isSupported()) {
					$supported = true;
					break;
				}
			}
		}
		$supported || throw new Nette\InvalidStateException("No supported handler specified in configuration");
		$args = [
			'assetsDir' => $config->assetsDir,
			'sourceDir' => $config->sourceDir,
			'wwwDir' => $this->getContainerBuilder()->parameters['wwwDir'],
			'factory' => $handlerStatement,
			'quality' => $config->quality
		];
		if($config->pipe instanceof Nette\DI\Definitions\Statement){
			$config->pipe->arguments = $args + $config->pipe->arguments;
		}
		$pipe = $builder->addDefinition($this->prefix('pipe'))
			->setFactory($config->pipe, $args)
			->addTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT);

		$this->provider = $builder->addDefinition($this->prefix('provider'))
			->setFactory($config->provider, [$pipe, array_reverse($config->middlewares)])
			->addTag(Nette\DI\Extensions\InjectExtension::TAG_INJECT);

		$builder->addDefinition($this->prefix('storage'))
			->setFactory($config->storage, [$config->sourceDir])
			//->setType($config->storage)
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
						'provider' => $this->provider,
						'macro' => $config->macro,
						'filter' => $config->filter === true ? $config->macro : $config->filter,
						'function' => $config->function === true ? $config->macro : $config->function,
						'tags' => $config->tags,
					])
				]);
			} else {
				$macro = ImagesMacros::class . '::install';
				$pipeName = "{$this->name}ImagePipe";
				$latte->addSetup('?->onCompile[] = function ($engine) { ' . $macro . '($engine->getCompiler(),?,?); }', ['@self', $pipeName, $config->macro]);
				$latte->addSetup('$service->addProvider(?,?)', [$pipeName, $this->provider]);
			}
		}
	}

}
