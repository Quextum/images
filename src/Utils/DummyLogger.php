<?php

namespace Quextum\Images\Utils;


use Tracy\ILogger;

final class DummyLogger implements ILogger
{

    function log($value, $level = self::INFO)
    {

    }
}
