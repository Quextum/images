<?php declare(strict_types=1);

namespace Quextum\Images\Latte;


use Latte;
use Latte\CompileException;
use Latte\Compiler\Nodes\FragmentNode;
use Nette\InvalidStateException;
use Quextum\Images\Pipes\ImagePipe;

class ImagesLatteExtension extends Latte\Extension
{

    protected ImagePipe $pipe;
    protected string $macroName;
    protected string $pipeName;

    public function __construct(ImagePipe $pipe, string $macroName, string $pipeName)
    {
        $this->macroName = $macroName;
        $this->pipeName = $pipeName;
        $this->pipe = $pipe;
    }

    public function getTags(): array
    {
        return [
            $this->macroName => [$this, 'createImg'],
            'n:' . $this->macroName => [$this, 'createNImg'],
        ];
    }

    public function createImg(Latte\Compiler\Tag $tag): Latte\Compiler\Node
    {
        $tag->parser->stream->tryConsume(',');
        $args = $tag->parser->parseArguments();
        return new Latte\Compiler\Nodes\AuxiliaryNode(
            fn(Latte\Compiler\PrintContext $context) => $context->format('echo %escape($this->global->%node->request(%node)));', $this->pipeName, $args)
        );
    }

    public function getProviders(): array
    {
        return [
            $this->pipeName => $this->pipe
        ];
    }

    public function createNImg(Latte\Compiler\Tag $tag): Latte\Compiler\Node
    {
        bdump($tag);
        bdump($tag->htmlElement->attributes);
        return new Latte\Compiler\Nodes\AuxiliaryNode(
            function (Latte\Compiler\PrintContext $context) use ($tag) {
                $start = '$response = $this->global->' . $this->pipeName . '->request(%node.args);';
                if ($tag->name === 'a') {
                    self::assertAttrs($tag->htmlElement->attributes, 'href');
                    $attrs = ['href' => '$response->src'];
                    return $context->format($start . self::write($attrs));
                }
                //self::assertAttrs($node, 'type');
                if ($tag->name === 'img') {
                    //self::assertAttrs($node, 'width', 'height');
                    $attrs = [
                        'src' => '$response->src',
                        'type' => '$response->mime',
                        'width' => '$response->size[0]',
                        'height' => '$response->size[1]',
                    ];

                    return $context->format($start . self::write(array_diff_key($attrs, $tag->htmlElement->attributes)));
                }
                if ($tag->name === 'source') {
                    $attrs = [
                        /* 'srcset' => '$response->src',*/
                        'type' => '$response->mime',
                    ];

                    $start . ' ?> srcset="<?php echo(%escape($response->src)); if(isset($response->size[0])){ echo " ",%escape($response->size[0]),"w"; } ?>" <?php ' . self::write(array_diff_key($attrs, $tag->htmlElement->attributes));

                    return $context->format($start);
                }
                throw new InvalidStateException("Unsupported node {$tag->name} supported are: a,img,source");

            }
        );


    }

    private static function write(array $props): string
    {
        $ret = [];
        foreach ($props as $attr => $code) {
            $ret[] = self::prepare($attr, $code);
        }
        return implode(' ', $ret);
    }

    private static function php(string $php): string
    {
        return "<?php $php ?>";
    }

    private static function html(string $html): string
    {
        return "?> $html <?php";
    }

    private static function prepare(string $attr, string $code): string
    {
        $php = self::php("echo(%escape($code));");
        $html = self::html("$attr=\"$php\"");
        return " if(isset($code)){ $html } ";
    }

    /**
     * @throws CompileException
     */
    private static function assertAttrs(FragmentNode $node, ...$attrs): void
    {
        foreach ($attrs as $attr) array_key_exists($attr, $node->children) && throw new CompileException("attribute $attr must not be set");
    }

}
