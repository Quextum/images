<?php

namespace Quextum\Images\Utils;

use Nette;
use Nette\Utils\Strings;

class Helpers
{

    use Nette\SmartObject;

    public const ARGS = ['name', 'size', 'flags', 'format', 'options'];

    public static function prepareMacroArguments($macro): array
    {
        $count = count(self::ARGS);
        $arguments = array_map('trim', explode(',', $macro, $count)) + array_fill(0, $count, null);
        return array_combine(self::ARGS, array_slice($arguments, 0, $count));
    }

    /**
     * @param string $relative
     * @return string
     * @latteFilter
     */
    public static function webalizePath(string $relative): string
    {
        $info = new \SplFileInfo($relative);
        $path = $info->getPath();
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        foreach ($parts as &$part) {
            $part = Strings::webalize($part);
        }
        $path = implode('/', $parts);
        $ext = $info->getExtension();
        $filename = $info->getBasename(".$ext");
        $filename = Strings::webalize($filename);
        $ext = Strings::lower($ext);
        return "$path/$filename.$ext";
    }

}
