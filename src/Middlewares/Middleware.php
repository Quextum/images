<?php declare(strict_types=1);

namespace Quextum\Images\Middlewares;

use Quextum\Images\Request;
use Quextum\Images\Result;

interface Middleware
{

    public function __invoke(Request $request, callable $next): Result;

}