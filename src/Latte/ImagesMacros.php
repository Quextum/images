<?php declare(strict_types=1);

namespace Quextum\Images\Latte;

use Latte\CompileException;
use Latte\Compiler;
use Latte\MacroNode;
use Latte\Macros\MacroSet;
use Latte\PhpWriter;
use Nette\InvalidStateException;

final class ImagesMacros extends MacroSet
{

    protected string $pipeName;

    /**
     * @param string $pipeName
     */
    public function setPipeName(string $pipeName): void
    {
        $this->pipeName = $pipeName;
    }

    public static function install(Compiler $compiler, string $pipeName, string $macro)
    {
        $me = new static($compiler);
        $me->setPipeName($pipeName);
        $me->addMacro($macro, [$me, 'macroImg'], null, [$me, 'macroAttrImg']);
        return $me;
    }

    /**
     * @param MacroNode $node
     * @param PhpWriter $writer
     * @return string
     * @throws CompileException
     */
    public function macroImg(MacroNode $node, PhpWriter $writer): string
    {
        return $writer->write('echo(%escape($this->global->' . $this->pipeName . '->request(%node.args)));');
    }


    /**
     * @param MacroNode $node
     * @param PhpWriter $writer
     * @return string
     * @throws CompileException
     */
    public function macroAttrImg(MacroNode $node, PhpWriter $writer): string
    {

        $start = '$response = $this->global->' . $this->pipeName . '->request(%node.args);';
        if ($node->htmlNode->name === 'a') {
            self::assertAttrs($node, 'href');
            $attrs = ['href' => '$response->src'];
            return $writer->write($start . self::write($attrs));
        }
        //self::assertAttrs($node, 'type');
        if ($node->htmlNode->name === 'img') {
            //self::assertAttrs($node, 'width', 'height');
            $attrs = [
                'src' => '$response->src',
                'type' => '$response->mime',
                'width' => '$response->size[0]',
                'height' => '$response->size[1]',
            ];

            return $writer->write($start . self::write(array_diff_key($attrs, $node->htmlNode->attrs)));
        }
        if ($node->htmlNode->name === 'source') {
            $attrs = [
                /* 'srcset' => '$response->src',*/
                'type' => '$response->mime',
            ];
            return $writer->write($start . ' ?> srcset="<?php echo(%escape($response->src)); if(isset($response->size[0])){ echo " ",%escape($response->size[0]),"w"; } ?>" <?php ' . self::write(array_diff_key($attrs, $node->htmlNode->attrs)));
        }
        throw new InvalidStateException("Unsupported node {$node->htmlNode->name} supported are: a,img,source");
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

    private static function assertAttrs(MacroNode $node, ...$attrs)
    {
        foreach ($attrs as $attr) array_key_exists($attr, $node->htmlNode->attrs) && throw new CompileException("attribute $attr must not be set");
    }

}
