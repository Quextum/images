<?php


namespace Quextum\Images\Utils;


use Tracy\Debugger;
use Tracy\ILogger;

final class BarDumpLogger implements ILogger
{

    function log($value, $level = self::INFO)
    {
        Debugger::barDump($value);

    }
}
