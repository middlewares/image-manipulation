<?php

namespace Middlewares\Tests;

use Middlewares\ImageManipulation;
use Middlewares\Utils\Dispatcher;
use Middlewares\Utils\Factory;
use PHPUnit\Framework\TestCase;

class ImageManipulationTest extends TestCase
{
    public function basePathProvider()
    {
        return [
            ['/subdirectory/of/images', '/assets/foto.jpg'],
            ['/subdirectory/of/images', '/foto.jpg'],
            ['', '/assets/foto.jpg'],
        ];
    }

    /**
     * @dataProvider basePathProvider
     * @param mixed $basePath
     * @param mixed $path
     */
    public function testImageManipulation($basePath, $path)
    {
        $key = uniqid();
        $uri = ImageManipulation::getUri($path, 'resizeCrop,50,50|format,png', $key);
        $request = Factory::createServerRequest([], 'GET', $basePath.$uri)
            ->withHeader('Accept', 'image/*');

        $response = Dispatcher::run([
            new ImageManipulation($key),
            function ($request) use ($basePath, $path) {
                $this->assertEquals($basePath.$path, $request->getUri()->getPath());
                $content = file_get_contents(
                    'https://upload.wikimedia.org/wikipedia/commons/5/58/Vaca_rubia_galega._Oroso_1.jpg'
                );

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
}
