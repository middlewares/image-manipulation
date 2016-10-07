<?php

namespace Middlewares\Tests;

use Middlewares\ImageManipulation;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;
use mindplay\middleman\Dispatcher;

class ImageManipulationTest extends \PHPUnit_Framework_TestCase
{
    public function testImageManipulation()
    {
        $key = uniqid();
        $path = '/assets/foto.jpg';
        $uri = ImageManipulation::getUri($path, 'resizeCrop,50,50|format,png', $key);
        $request = (new Request($uri))->withHeader('Accept', 'image/*');

        $response = (new Dispatcher([
            new ImageManipulation($key),
            function ($request) use ($path) {
                $this->assertEquals($path, $request->getUri()->getPath());

                return (new Response())->withBody(new Stream(__DIR__.$path));
            },
        ]))->dispatch($request);

        $this->assertEquals('png', pathinfo($uri, PATHINFO_EXTENSION));
        $this->assertInstanceOf('Psr\\Http\\Message\\ResponseInterface', $response);
        $this->assertEquals('image/png', $response->getHeaderLine('Content-Type'));

        $info = getimagesizefromstring((string) $response->getBody());

        $this->assertEquals(50, $info[0]);
        $this->assertEquals(50, $info[1]);
        $this->assertEquals(IMAGETYPE_PNG, $info[2]);
    }
}
