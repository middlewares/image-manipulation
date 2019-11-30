# middlewares/image-manipulation

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-travis]][link-travis]
[![Quality Score][ico-scrutinizer]][link-scrutinizer]
[![Total Downloads][ico-downloads]][link-downloads]

Middleware to transform images on demand, allowing resize, crop, rotate and transform to other formats. Uses [imagecow](https://github.com/oscarotero/imagecow) library that can detect and use `Gd` and `Imagick`, and also has support for [client hints](https://www.smashingmagazine.com/2016/01/leaner-responsive-images-client-hints/) and different [automatic cropping methods](https://github.com/oscarotero/imagecow#automatic-cropping).

The uri is generated encoding the image path and the manipulation options with [lcobucci/jwt](https://github.com/lcobucci/jwt/), to prevent alterations and image-resize attacks.

**Note:** To keep the [SRP](https://en.wikipedia.org/wiki/Single_responsibility_principle), this middleware does not provide the following functionalities, that should be delegated to other middleware:

* **Read the image from a directory:** this library just manipulate the image response returned by inner middlewares, does NOT read in the filesystem.

* **Image caching:** The library returns a response with the manipulated image but does NOT provide any caching system.

It's possible to combine this library with [middlewares/filesystem](https://github.com/middlewares/filesystem) that allows to read and write to the filesystem. (See [example](#example) below).

## Requirements

* PHP >= 7.2
* A [PSR-7 http library](https://github.com/middlewares/awesome-psr15-middlewares#psr-7-implementations)
* A [PSR-15 middleware dispatcher](https://github.com/middlewares/awesome-psr15-middlewares#dispatcher)

## Installation

This package is installable and autoloadable via Composer as [middlewares/image-manipulation](https://packagist.org/packages/middlewares/image-manipulation).

```sh
composer require middlewares/image-manipulation
```

## Example

The following example uses also [middlewares/filesystem](https://github.com/middlewares/filesystem) to read/save the manipulated images.

```php
use Middlewares\ImageManipulation;
use Middlewares\Reader;
use Middlewares\Writer;

//You need a signature key
$key = 'sdf6&-$<@#asf';

//Manipulated images directory
$cachePath = '/path/to/cache';

//Original images directory
$imagePath = '/path/to/images';

$dispatcher = new Dispatcher([
    //read and returns the manipulated image if it's currently cached
    Reader::createFromDirectory($cachePath)->continueOnError(),

    //saves the manipulated images returned by the next middleware
    Writer::createFromDirectory($cachePath),

    //transform the image
    new Middlewares\ImageManipulation($key),

    //read and return a response with original image if exists
    Reader::createFromDirectory($imagePath)->continueOnError(),

    //In your views
    function () {
        //Create a manipulated image uri
        $uri = Middlewares\ImageManipulation::getUri('image.jpg', 'resizeCrop,500,500,CROP_ENTROPY');

        echo "<img src='{$uri}' alt='Manipulated image' width=500 height=500>";
    }
]);

$response = $dispatcher->dispatch(new ServerRequest($uri));
```

## Usage

You need a key to sign the uri. This prevent attacks and alterations to the path. 

```php
$key = 'super-secret-key';

$imageManipulation = new Middlewares\ImageManipulation($key);
```

Optionally, you can provide a `Psr\Http\Message\StreamFactoryInterface` as the second argument to create the new response stream with the image. If it's not defined, [Middleware\Utils\Factory](https://github.com/middlewares/utils#factory) will be used to detect it automatically.

```php
$key = 'super-secret-key';
$streamFactory = new MyOwnStreamFactory();

$imageManipulation = new Middlewares\ImageManipulation($key, $streamFactory);
```

### clientHints

This option allows to use client hints, that is disabled by default. If this method is called with the default arguments, the allowed hints are `['Dpr', 'Viewport-Width', 'Width']`. Note that client hints [are supported only by Chrome and Opera browsers](http://caniuse.com/#feat=client-hints-dpr-width-viewport)

### library

The library to use. It can be `Gd` or `Imagick`. It's autodetected if it's not specified.

## Helpers

### getUri

To ease the uri creation this static method is provided, accepting three arguments:

* `$image`: The image path. This value is used to replace the uri's path of the request to the next middlewares.
* `$transform`: The transformation details. You can use any [method of imagecow api](https://github.com/oscarotero/imagecow#execute-multiple-functions) as a string, for example:
  * `resize,200`: Resize the image to 200px width (automatic height)
  * `crop,200,500`: Crop the image to 200x500px (centered)
  * `crop,100,100,CROP_ENTROPY`: Crop the image to 100x100px using the entropy method to find the most interesting point of the image
  * `resize,300|rotate,90|format,jpg`: Resize the image to 300px width, rotate 90º and convert to jpg
* `$signatureKey`: Optional signature key to sign the uri path. If it's not provided, use the same key passed to the middleware.

```php
use Middlewares\ImageManipulation;

$image = '/img/avatar.jpg';
$transform = 'resizeCrop,200,200';

$uri = ImageManipulation::getUri($image, $transform);

echo '<img src="'.$uri.'" alt="My image">';
```

---

Please see [CHANGELOG](CHANGELOG.md) for more information about recent changes and [CONTRIBUTING](CONTRIBUTING.md) for contributing details.

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

[ico-version]: https://img.shields.io/packagist/v/middlewares/image-manipulation.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/middlewares/image-manipulation/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/g/middlewares/image-manipulation.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/middlewares/image-manipulation.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/middlewares/image-manipulation
[link-travis]: https://travis-ci.org/middlewares/image-manipulation
[link-scrutinizer]: https://scrutinizer-ci.com/g/middlewares/image-manipulation
[link-downloads]: https://packagist.org/packages/middlewares/image-manipulation
