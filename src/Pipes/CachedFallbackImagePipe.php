<?php declare(strict_types=1);

namespace Quextum\Images\Pipes;


class CachedFallbackImagePipe extends FallbackImagePipe
{

	use TCachedImagePipe;
}
