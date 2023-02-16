<?php

namespace Quextum\Images\Utils;

use Nette;
use Nette\InvalidArgumentException;
use Nette\Utils\Image as NImage;
use Nette\Utils\Strings;

class Helpers
{

    use Nette\SmartObject;

    public const FLAGS = [
        'fit' => NImage::FIT,
        'fill' => NImage::FILL,
        'exact' => NImage::EXACT,
        'shrink' => NImage::SHRINK_ONLY,
        'stretch' => NImage::STRETCH,
    ];

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
        $path = $path ? $path . '/' : "";
        $ext = $info->getExtension();
        $filename = $info->getBasename(".$ext");
        $filename = Strings::webalize($filename);
        $ext = Strings::lower($ext);
        return "$path$filename.$ext";
    }

    public static function transformFlags(&$flags): void
    {
        if (empty($flags)) {
            $flags = NImage::FIT;
        } elseif (!is_int($flags)) {
            $_flags = explode('_', strtolower($flags));
            $flags = 0;
            foreach ($_flags as $flag) {
                $flags |= self::FLAGS[$flag];
            }
            if (!isset($flags)) {
                throw new InvalidArgumentException('Mode is not allowed');
            }
        }
    }


    public static function callbackDelayed(callable $callback): void
    {
        register_shutdown_function(static fn() => register_shutdown_function($callback));
    }

    public static function callbackAfterRequest(callable $callback): void
    {
        self::callbackDelayed(static function () use ($callback) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            $callback();
        });
    }

}
