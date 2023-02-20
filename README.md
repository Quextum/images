# Quextum Images

# Usage

```neon
extensions:
	images: Quextum\Images\DI\ImagesExtension

images:
	sourceDir: %wwwDir%/../data/images
	assetsDir: %wwwDir%/media
```

# Callbacks

## Before

Transform arguments

```php 
function transformArguments(Quextum\Images\Request $request)
{
    if ($request->image instanceof Nette\Database\Table\ActiveRow) {
        $row = $request->image;
        $request->image = "$row->namespace/$row->filename";
    }
    if ($request->image === 'Tvoje máma') {
        $request->image = "TVOJE_MAMA.jpg";
    }
}
```

```neon
decorator:
    Quextum\Images\Pipes\ImagePipe:
        setup:
            - '$onBeforeRequest[]' = transformArguments
```

## After

Optimizing images with Spatie optimizer

```neon
services:
	optimizer: Spatie\ImageOptimizer\OptimizerChainFactory::create

decorator:
    Quextum\Images\Pipes\ImagePipe:
        setup:
            - '$onAfterSave[]' = [@optimizer,optimize]
```

## Quality

```neon
images:
	quality:
		default: 90
		jpg: 90
		webp: 100
		avif: 100
```

## Middlewares
[CachingMiddleware](src/Middlewares/CachingMiddleware.php) is used as default.
### Fallback image middleware
```neon
images:
	middlewares:
	    - Quextum\Images\Middlewares\FallbackMiddleware
```
