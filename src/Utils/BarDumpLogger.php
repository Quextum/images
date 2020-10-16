<?php


namespace App\Images\Utils;


use Tracy\Debugger;
use Tracy\ILogger;

final class BarDumpLogger implements ILogger
{

	function log($value, $level = self::INFO)
	{
		if (class_exists(Debugger::class)) {
			Debugger::barDump($value);
		}
	}
}
