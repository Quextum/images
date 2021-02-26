<?php

namespace Quextum\Images\Macros;

use Quextum\Images\Utils\Helpers;
use Latte\CompileException;
use Latte\Compiler;
use Latte\MacroNode;
use Latte\Macros\MacroSet;
use Latte\PhpWriter;


/**
 * @author Jan Brabec <brabijan@gmail.com>
 * @author Filip Procházka <filip@prochazka.su>
 */
class Latte extends MacroSet
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
        return $writer->write('echo %escape($this->global->' . $this->pipeName . '->request(%node.args))');
    }


    /**
     * @param MacroNode $node
     * @param PhpWriter $writer
     * @return string
     * @throws CompileException
     */
    public function macroAttrImg(MacroNode $node, PhpWriter $writer): string
    {

        $start = '$response = $this->global->' . $this->pipeName . '->request(%node.args); ?>';
        if ($node->htmlNode->name === 'a') {
            return $writer->write($start.'
            href="<?php echo %escape($response->src) ?>" 
            <?php ');
        }
        self::assertAttrs($node, 'type');
        if ($node->htmlNode->name === 'img') {
            self::assertAttrs($node, 'width', 'height');
            return $writer->write($start.'
            src="<?php echo %escape($response->src) ?>" 
            type="<?php echo %escape($response->mime) ?>" 
            width="<?php echo %escape($response->size[0]) ?>" 
            height="<?php echo %escape($response->size[1]) ?>" <?php ');
        }
        if ($node->htmlNode->name === 'source') {
            return $writer->write($start.'
            srcset="<?php echo %escape($response->src) ?> <?php echo %escape($response->size[0]) ?>w" 
            type="<?php echo %escape($response->mime) ?>" 
            <?php ');
        }

    }

    private static function assertAttrs(MacroNode $node, ...$attrs)
    {
        foreach ($attrs as $attr) array_key_exists($attr, $node->htmlNode->attrs) && throw new CompileException("attribute $attr must not be set");
    }

}
