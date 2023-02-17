<?php declare(strict_types=1);

namespace Quextum\Images\Latte;

use Latte;
use Latte\CompileException;
use Nette\InvalidArgumentException;
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
        private Executor    $executor,
        private string      $macro,
        private string|null|false $filter,
        private string|null|false $function,
        private array       $tags = self::TAGS,
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
        $tag->expectArguments();
        return new class(
            $this->getProviderName(),
            $tag->parser->parseArguments()->toArguments(),
            $tag->isNAttribute(),
            $tag->htmlElement->name,
            $this->tags
        ) extends Latte\Compiler\Nodes\StatementNode {


            public function __construct(
                public string $pipeName,
                public array  $arguemnts,
                public bool   $nAttr,
                public string $tag,
                public array  $tags,
            )
            {
            }

            public function print(Latte\Compiler\PrintContext $context): string
            {
                $shit = implode(',', array_fill(0, count($this->arguemnts), '%node'));
                if ($this->nAttr) {
                    $params = $this->tags[$this->tag] ?? throw new InvalidArgumentException("Tag $this->tag is not supported. Supported are: " . implode(', ', array_keys($this->tags)));
                    $attr = $params[0];
                    $knownAttrs = [
                        $attr => 'src',
                        'width' => 'size[0]',
                        'height' => 'size[1]',
                        'type' => 'mime'
                    ];
                    $attributes = array_intersect_key($knownAttrs, array_flip($params));
                    $attrString = implode(' ', Arrays::map($attributes, static fn($source, $attr) => "echo(\" $attr=\".%escape(\$response->$source));"));
                    return $context->format("%line \$response = \$this->global->{$this->pipeName}->request($shit) ;  $attrString ", $this->position, ...$this->arguemnts);
                }
                return $context->format("%line \$response = \$this->global->{$this->pipeName}->request($shit) ;  echo %escape(\$response->src); ", $this->position, ...$this->arguemnts);
            }

            public function &getIterator(): \Generator
            {
                foreach ($this->arguemnts as $arguemnt) {
                    yield $arguemnt;
                }
            }
        };
    }
}
