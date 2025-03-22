<?php
declare(strict_types = 1);

namespace Middlewares;

use Exception;
use Imagecow\Image;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Middlewares\Utils\Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

class ImageManipulation implements MiddlewareInterface
{
    public const MAX_FILENAME_LENGTH = 200;
    public const DATA_CLAIM = 'im';
    public const BASE_PATH = '/_/';

    /**
     * @var string|null
     */
    private static $currentSignatureKey;

    /**
     * @var string|null
     */
    private $library;

    /**
     * @var string
     */
    private $signatureKey;

    /**
     * @var string[]|false Enable client hints
     */
    private $clientHints = false;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * Build a new uri with the payload.
     *
     * @param non-empty-string|null $signatureKey
     */
    public static function getUri(string $path, string $transform, ?string $signatureKey = null): string
    {
        $signatureKey = $signatureKey ?: self::$currentSignatureKey;

        if ($signatureKey === null) {
            throw new RuntimeException('No signature key provided!'.
                ' You must instantiate the middleware or assign the key as third argument');
        }

        preg_match('/format,(\w+)/', $transform, $matches);

        if (empty($matches[1])) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
        } else {
            $extension = $matches[1];
        }

        $config = Configuration::forSymmetricSigner(
            new Sha256(),
            InMemory::plainText($signatureKey)
        );

        $token = $config->builder()
            ->withClaim(self::DATA_CLAIM, [$path, $transform])
            /* @throws \Lcobucci\JWT\Signer\InvalidKeyProvided quick reminder */
            ->getToken($config->signer(), $config->signingKey())
            ->toString();

        return self::BASE_PATH.substr($token.'/', 0, -1).'.'.$extension;
    }

    /**
     * Set the signature key used to encode/decode the data.
     */
    public function __construct(string $signatureKey, ?StreamFactoryInterface $streamFactory = null)
    {
        $this->signatureKey = $signatureKey;
        $this->streamFactory = $streamFactory ?: Factory::getStreamFactory();
    }

    /**
     * Enable the client hints.
     *
     * @param string[] $clientHints
     */
    public function clientHints(array $clientHints = ['Dpr', 'Viewport-Width', 'Width']): self
    {
        $this->clientHints = $clientHints;

        return $this;
    }

    /**
     * Force the use of a library (Imagick | Gd)
     */
    public function library(string $library): self
    {
        $this->library = $library;

        return $this;
    }

    /**
     * Process a request and return a response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $previousSignatureKey = self::$currentSignatureKey;
        self::$currentSignatureKey = $this->signatureKey;

        if (strpos($request->getHeaderLine('Accept'), 'image/') === false) {
            $response = $handler->handle($request);
        } else {
            $uri = $request->getUri();
            $payload = self::getPayload($uri->getPath());

            if (!$payload) {
                $response = $handler->handle($request);
            } else {
                list($path, $transform) = $payload;

                $request = $request->withUri($uri->withPath($path));
                $response = $handler->handle($request);

                if (!empty($this->clientHints)) {
                    $response = $response->withHeader('Accept-CH', implode(',', $this->clientHints));
                }

                $size = $response->getBody()->getSize();

                if ($response->getStatusCode() === 200 && ($size === null || $size > 1)) {
                    $response = $this->transform($response, $transform, $this->getClientHints($request));
                }
            }
        }

        self::$currentSignatureKey = $previousSignatureKey;

        return $response;
    }

    /**
     * Transform the image.
     *
     * @param array<string,string>|null $hints
     */
    private function transform(ResponseInterface $response, string $transform, ?array $hints = null): ResponseInterface
    {
        $image = Image::fromString((string) $response->getBody(), $this->library);

        if ($hints) {
            $image->setClientHints($hints);
            $response = $response->withHeader('Vary', implode(', ', $hints));
        }

        $image->transform($transform);

        $body = $this->streamFactory->createStream($image->getString());

        return $response
            ->withBody($body)
            ->withHeader('Content-Type', $image->getMimeType());
    }

    /**
     * Returns the client hints sent.
     *
     * @return array<string,string>|null
     */
    private function getClientHints(ServerRequestInterface $request): ?array
    {
        if (empty($this->clientHints)) {
            return null;
        }

        $hints = [];

        foreach ($this->clientHints as $name) {
            if ($request->hasHeader($name)) {
                $hints[$name] = $request->getHeaderLine($name);
            }
        }

        return $hints;
    }

    /**
     * Parse and return the payload.
     *
     * @return array{0: string, 1:string}
     */
    private function getPayload(string $path): ?array
    {
        if (strpos($path, self::BASE_PATH) === false) {
            return null;
        }

        try {
            list($basePath, $token) = explode(self::BASE_PATH, $path, 2);

            if ($extensionPos = strrpos($token, '.')) {
                $token = substr($token, 0, $extensionPos);
            }

            $config = Configuration::forSymmetricSigner(
                new Sha256(),
                InMemory::plainText($this->signatureKey)
            );

            $token = $config->parser()->parse(str_replace('/', '', $token));
            if (!$config->validator()->validate(
                $token,
                ...[new SignedWith($config->signer(), $config->signingKey())]
            )) {
                return null;
            }

            /** @phpstan-ignore-next-line */
            $payload = $token->claims()->get(self::DATA_CLAIM);

            /* @phpstan-ignore-next-line */
            if ($payload) {
                /* @phpstan-ignore-next-line */
                $payload[0] = str_replace('//', '/', '/'.$basePath.'/'.$payload[0]);
            }

            return $payload;
        } catch (Exception $exception) {
            return null;
        }
    }
}
