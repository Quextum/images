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


    public static function install(Compiler $compiler)
    {
        $me = new static($compiler);
        $me->addMacro('img', [$me, 'macroImg'], null, [$me, 'macroAttrImg']);
        $me->addMacro('imgSet', [$me, 'macroImgSet'], null, [$me, 'macroAttrImgSet']);
        return $me;
    }

    public function macroImgSet(MacroNode $node, PhpWriter $writer): string
    {
        $command = '$this->global->imagePipe->requestSrcSet(' . $node->args . ')';
        return $writer->write('echo %escape(' . $writer->formatWord($command) . ')');
    }


    public function macroAttrImgSet(MacroNode $node, PhpWriter $writer): string
    {
        $command = '$this->global->imagePipe->requestSrcSet(' . $node->args . ')';
        return $writer->write('?> src="<?php echo %escape(' . $writer->formatWord($command) . ')?>" <?php ');
    }


    /**
     * @param MacroNode $node
     * @param PhpWriter $writer
     * @return string
     * @throws CompileException
     */
    public function macroImg(MacroNode $node, PhpWriter $writer): string
    {
        $arguments = Helpers::prepareMacroArguments($node->args);
        if ($arguments['name'] === null) {
            throw new CompileException('Please provide filename.');
        }

        // $namespace = $arguments['namespace'];
        //unset($arguments['namespace']);
        $arguments = array_map(function ($value) use ($writer) {
            return $value ? $writer->formatWord($value) : 'NULL';
        }, $arguments);

        $command = '$this->global->imagePipe';
        // $command .= $namespace !== NULL ? '->setNamespace(' . $writer->formatWord(trim($namespace)) . ')' : '';
        $command .= '->request(' . implode(', ', $arguments) . ')';

        return $writer->write('echo %escape(' . $writer->formatWord($command) . ')');
    }


    /**
     * @param MacroNode $node
     * @param PhpWriter $writer
     * @return string
     * @throws CompileException
     */
    public function macroAttrImg(MacroNode $node, PhpWriter $writer): string
    {
        $arguments = Helpers::prepareMacroArguments($node->args);
        if ($arguments['name'] === null) {
            throw new CompileException('Please provide filename.');
        }

        //$namespace = $arguments['namespace'];
        //unset($arguments['namespace']);
        $arguments = array_map(function ($value) use ($writer) {
            return $value ? $writer->formatWord($value) : 'NULL';
        }, $arguments);

        $command = '$this->global->imagePipe';
        //$command .= $namespace !== NULL ? '->setNamespace(' . $writer->formatWord(trim($namespace)) . ')' : '';
        $command .= '->request(' . implode(', ', $arguments) . ')';

        return $writer->write('?> src="<?php echo %escape(' . $writer->formatWord($command) . ')?>" <?php ');
    }


}
