<?php
declare(strict_types = 1);

namespace Middlewares\Tests;

use Middlewares\ImageManipulation;
use Middlewares\Utils\Dispatcher;
use Middlewares\Utils\Factory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ImageManipulationTest extends TestCase
{
    public function testNoSignatureException()
    {
        $this->expectException(RuntimeException::class);

        $uri = ImageManipulation::getUri('/foto.jpg', 'resize,200');
    }

    public function basePathProvider(): array
    {
        return [
            ['/subdirectory/of/images', '/assets/foto.jpg'],
            ['/subdirectory/of/images', '/foto.jpg'],
            ['', '/assets/foto.jpg'],
        ];
    }

    /**
     * @dataProvider basePathProvider
     */
    public function testImageManipulation(string $basePath, string $path)
    {
        $key = uniqid();
        $uri = ImageManipulation::getUri($path, 'resizeCrop,50,50|format,png', $key);
        $request = Factory::createServerRequest([], 'GET', $basePath.$uri)
            ->withHeader('Accept', 'image/*');

        $response = Dispatcher::run([
            new ImageManipulation($key),
            function ($request) use ($basePath, $path) {
                $this->assertEquals($basePath.$path, $request->getUri()->getPath());
                $content = file_get_contents(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');

                $response = Factory::createResponse();
                $response->getBody()->write($content);

                return $response;
            },
        ], $request);

        $this->assertEquals('png', pathinfo($uri, PATHINFO_EXTENSION));
        $this->assertEquals('image/png', $response->getHeaderLine('Content-Type'));

        $info = getimagesizefromstring((string) $response->getBody());

        $this->assertEquals(50, $info[0]);
        $this->assertEquals(50, $info[1]);
        $this->assertEquals(IMAGETYPE_PNG, $info[2]);
    }

    public function testClientHint()
    {
        $key = uniqid();
        $uri = ImageManipulation::getUri('/foto.jpg', 'resize,200', $key);

        $response = Dispatcher::run(
            [
                (new ImageManipulation($key))
                    ->clientHints(),

                function ($request) {
                    echo file_get_contents(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');
                },
            ],
            Factory::createServerRequest([], 'GET', $uri)
                ->withHeader('Accept', 'image/*')
                ->withHeader('Width', '50')
        );

        $this->assertEquals('jpg', pathinfo($uri, PATHINFO_EXTENSION));
        $this->assertEquals('Dpr,Viewport-Width,Width', $response->getHeaderLine('Accept-CH'));

        $info = getimagesizefromstring((string) $response->getBody());

        $this->assertEquals(50, $info[0]);
        $this->assertEquals(IMAGETYPE_JPEG, $info[2]);
    }

    public function testNoAcceptHeader()
    {
        $key = uniqid();
        $uri = ImageManipulation::getUri('/foto.jpg', 'resize,200', $key);

        $response = Dispatcher::run(
            [
                new ImageManipulation($key),
                function () {
                    echo 'Foo';
                },
            ],
            Factory::createServerRequest([], 'GET', $uri)
        );

        $this->assertFalse($response->hasHeader('Content-Type'));
        $this->assertEquals('Foo', (string) $response->getBody());
    }

    public function testNotFound()
    {
        $key = uniqid();
        $uri = ImageManipulation::getUri('/foto.jpg', 'resize,200', $key);

        $response = Dispatcher::run(
            [
                new ImageManipulation($key),
                function () {
                    return Factory::createResponse(404);
                },
            ],
            Factory::createServerRequest([], 'GET', $uri)
                ->withHeader('Accept', 'image/*')
        );

        $this->assertFalse($response->hasHeader('Content-Type'));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testInvalidUri()
    {
        $response = Dispatcher::run(
            [
                new ImageManipulation(uniqid()),

                function ($request) {
                    echo file_get_contents(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');
                },
            ],
            Factory::createServerRequest([], 'GET', '/_/invalid-url.jpg')
                ->withHeader('Accept', 'image/*')
        );

        $info = getimagesizefromstring((string) $response->getBody());
        $original = getimagesize(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');

        $this->assertEquals($original, $info);
    }

    public function testNoBasePath()
    {
        $response = Dispatcher::run(
            [
                new ImageManipulation(uniqid()),

                function ($request) {
                    echo file_get_contents(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');
                },
            ],
            Factory::createServerRequest([], 'GET', '/invalid-url.jpg')
                ->withHeader('Accept', 'image/*')
        );

        $info = getimagesizefromstring((string) $response->getBody());
        $original = getimagesize(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');

        $this->assertEquals($original, $info);
    }

    public function testInvalidToken()
    {
        $uri = ImageManipulation::getUri('/foto.jpg', 'resize,200', 'foo');
        $response = Dispatcher::run(
            [
                new ImageManipulation('bar'),

                function ($request) {
                    echo file_get_contents(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');
                },
            ],
            Factory::createServerRequest([], 'GET', $uri)
                ->withHeader('Accept', 'image/*')
        );

        $info = getimagesizefromstring((string) $response->getBody());
        $original = getimagesize(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');

        $this->assertEquals($original, $info);
    }
}
