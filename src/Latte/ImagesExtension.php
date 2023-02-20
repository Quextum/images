<?php declare(strict_types=1);

namespace Quextum\Images\Latte;

use Latte;
use Latte\CompileException;
use Nette\Utils\Arrays;
use Quextum\Images\Pipes\Executor;

/**
 * Class ImagesExtension
 * @package App\Images\DI
 */
class ImagesExtension extends Latte\Extension
{

	public const TAGS = [
		'meta' => ['content'],
		'a' => ['href'],
		'img' => ['src', 'width', 'height', 'type'],
		'source' => ['srcset'],
	];

	public function __construct(
		private Executor          $executor,
		private string            $macro,
		private string|null|false $filter,
		private string|null|false $function,
		private array             $tags = self::TAGS,
	)
	{
	}

	public function getFilters(): array
	{
		return $this->filter ? [
			$this->filter => [$this->executor, 'request'],
		] : [];
	}

	public function getFunctions(): array
	{
		return $this->function ? [
			$this->function => [$this->executor, 'request'],
		] : [];
	}

	public function getTags(): array
	{
		return [
			$this->macro => [$this, 'createNode'],
			"n:$this->macro" => [$this, 'createNode']
		];
	}

	public function getProviders(): array
	{
		return [$this->getProviderName() => $this->executor];
	}

	public function getProviderName(): string
	{
		return $this->macro . 'Provider';
	}

	/**  @throws CompileException */
	public function createNode(Latte\Compiler\Tag $tag): ?Latte\Compiler\Nodes\StatementNode
	{
		$tag->outputMode = $tag::OutputKeepIndentation;
		if ($tag->isNAttribute()) {
			$attributes = [
				'width' => $tag->htmlElement->getAttribute('width'),
				'height' => $tag->htmlElement->getAttribute('height'),
				'type' => $tag->htmlElement->getAttribute('type'),
			];
		}
		$tag->expectArguments();
		return new class(
			$this->getProviderName(),
			$tag->parser->parseArguments()->toArguments(),
			$tag->isNAttribute(),
			$tag->htmlElement->name,
			$this->tags,
			$attributes ?? []
		) extends Latte\Compiler\Nodes\StatementNode {

			public function __construct(
				public string $providerName,
				public array  $arguments,
				public bool   $nAttr,
				public string $tag,
				public array  $tags,
				public array  $attributes,
			)
			{
			}

			public function print(Latte\Compiler\PrintContext $context): string
			{
				$shit = implode(',', array_fill(0, count($this->arguments), '%node'));
				if ($this->nAttr) {
					$params = $this->tags[$this->tag] ?? ['src'];//?? throw new InvalidArgumentException("Tag $this->tag is not supported. Supported are: " . implode(', ', array_keys($this->tags)));
					$attr = $params[0];
					$knownAttrs = array_diff_key([
						$attr => '$response->src',
						'width' => $this->attributes['height'] && !$this->attributes['width'] ? "{$this->attributes['height']}/\$response->size[1] * \$response->size[0]" : '$response->size[0]',
						'height' => $this->attributes['width'] && !$this->attributes['height'] ?  "{$this->attributes['width']}/\$response->size[0] * \$response->size[1]" : '$response->size[1]',
						'type' => '$response->mime'
					], array_filter($this->attributes));
					$attributes = array_intersect_key($knownAttrs, array_flip($params));
					$attrString = implode(' ', Arrays::map($attributes, static fn($source, $attr) => "echo(\" $attr=\".%escape($source));"));
					return $context->format("%line \$response = \$this->global->{$this->providerName}->request($shit) ;  $attrString ", $this->position, ...$this->arguments);
				}
				return $context->format("%line \$response = \$this->global->{$this->providerName}->request($shit) ;  echo %escape(\$response->src); ", $this->position, ...$this->arguments);
			}

			public function &getIterator(): \Generator
			{
				foreach ($this->arguments as $argument) {
					yield $argument;
				}
			}
		};
	}
}
