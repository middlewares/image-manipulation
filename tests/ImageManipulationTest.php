<?php
declare(strict_types = 1);

namespace Middlewares\Tests;

use Lcobucci\JWT\Signer\InvalidKeyProvided;
use Middlewares\ImageManipulation;
use Middlewares\Utils\Dispatcher;
use Middlewares\Utils\Factory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ImageManipulationTest extends TestCase
{
    public function testNoSignatureException(): void
    {
        $this->expectException(RuntimeException::class);

        $uri = ImageManipulation::getUri('/foto.jpg', 'resize,200');
    }

    /**
     * @return array<string[]>
     */
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
    public function testImageManipulation(string $basePath, string $path): void
    {
        $key = sodium_crypto_aead_aes256gcm_keygen();
        $uri = ImageManipulation::getUri($path, 'resizeCrop,50,50|format,png', $key);
        $request = Factory::createServerRequest('GET', $basePath.$uri)
            ->withHeader('Accept', 'image/*');

        $response = Dispatcher::run([
            new ImageManipulation($key),
            function ($request) use ($basePath, $path) {
                $this->assertEquals($basePath.$path, $request->getUri()->getPath());
                $content = file_get_contents(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');

                $response = Factory::createResponse();
                /* @phpstan-ignore-next-line */
                $response->getBody()->write($content);

                return $response;
            },
        ], $request);

        $this->assertEquals('png', pathinfo($uri, PATHINFO_EXTENSION));
        $this->assertEquals('image/png', $response->getHeaderLine('Content-Type'));

        $info = getimagesizefromstring((string) $response->getBody());

        /* @phpstan-ignore-next-line */
        $this->assertEquals(50, $info[0]);
        /* @phpstan-ignore-next-line */
        $this->assertEquals(50, $info[1]);
        /* @phpstan-ignore-next-line */
        $this->assertEquals(IMAGETYPE_PNG, $info[2]);
    }

    public function testClientHint(): void
    {
        $key = sodium_crypto_aead_aes256gcm_keygen();
        $uri = ImageManipulation::getUri('/foto.jpg', 'resize,200', $key);

        $response = Dispatcher::run(
            [
                (new ImageManipulation($key))
                    ->clientHints(),

                function ($request) {
                    echo file_get_contents(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');
                },
            ],
            Factory::createServerRequest('GET', $uri)
                ->withHeader('Accept', 'image/*')
                ->withHeader('Width', '50')
        );

        $this->assertEquals('jpg', pathinfo($uri, PATHINFO_EXTENSION));
        $this->assertEquals('Dpr,Viewport-Width,Width', $response->getHeaderLine('Accept-CH'));

        $info = getimagesizefromstring((string) $response->getBody());
        /* @phpstan-ignore-next-line */
        $this->assertEquals(50, $info[0]);
    }

    public function testNoAcceptHeader(): void
    {
        $key = sodium_crypto_aead_aes256gcm_keygen();
        $uri = ImageManipulation::getUri('/foto.jpg', 'resize,200', $key);

        $response = Dispatcher::run(
            [
                new ImageManipulation($key),
                function () {
                    echo 'Foo';
                },
            ],
            Factory::createServerRequest('GET', $uri)
        );

        $this->assertFalse($response->hasHeader('Content-Type'));
        $this->assertEquals('Foo', (string) $response->getBody());
    }

    public function testNotFound(): void
    {
        $key = sodium_crypto_aead_aes256gcm_keygen();
        $uri = ImageManipulation::getUri('/foto.jpg', 'resize,200', $key);

        $response = Dispatcher::run(
            [
                new ImageManipulation($key),
                function () {
                    return Factory::createResponse(404);
                },
            ],
            Factory::createServerRequest('GET', $uri)
                ->withHeader('Accept', 'image/*')
        );

        $this->assertFalse($response->hasHeader('Content-Type'));
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testInvalidUri(): void
    {
        $response = Dispatcher::run(
            [
                new ImageManipulation(uniqid()),

                function ($request) {
                    echo file_get_contents(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');
                },
            ],
            Factory::createServerRequest('GET', '/_/invalid-url.jpg')
                ->withHeader('Accept', 'image/*')
        );

        $info = getimagesizefromstring((string) $response->getBody());
        $original = getimagesize(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');

        $this->assertEquals($original, $info);
    }

    public function testNoBasePath(): void
    {
        $response = Dispatcher::run(
            [
                new ImageManipulation(uniqid()),

                function ($request) {
                    echo file_get_contents(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');
                },
            ],
            Factory::createServerRequest('GET', '/invalid-url.jpg')
                ->withHeader('Accept', 'image/*')
        );

        $info = getimagesizefromstring((string) $response->getBody());
        $original = getimagesize(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');

        $this->assertEquals($original, $info);
    }

    public function testInvalidToken(): void
    {
        static::expectException(InvalidKeyProvided::class);
        $uri = ImageManipulation::getUri('/foto.jpg', 'resize,200', 'foo');

        $response = Dispatcher::run(
            [
                new ImageManipulation('bar'),

                function ($request) {
                    echo file_get_contents(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');
                },
            ],
            Factory::createServerRequest('GET', $uri)
                ->withHeader('Accept', 'image/*')
        );

        $info = getimagesizefromstring((string) $response->getBody());
        $original = getimagesize(__DIR__.'/assets/vaca_rubia_galega_oroso.jpg');

        $this->assertEquals($original, $info);
    }

    public function testWebp(): void
    {
        $uri = ImageManipulation::getUri('/foto.jpg', 'resize,200|format,webp', '3508e2c96f7c4cd0'
                                                   .'31a119514a50ff684729bc109889b3b6c4eff157f8cfda27');

        $this->assertEquals(
            '/_/eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpbSI6WyIvZm90by5qcGciLCJyZXNpemUs'
            .'MjAwfGZvcm1hdCx3ZWJwIl19.R-ahvCSRU1SGzrTxI2sFdHDjA8kreRSSlA_a1jHD0e4.webp',
            $uri
        );
    }
}
