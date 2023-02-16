<?php declare(strict_types=1);

namespace Quextum\Images\Pipes;

use Quextum\Images\Request;
use Quextum\Images\Result;

interface IImagePipe
{
    public function process(Request $request): Result;
}