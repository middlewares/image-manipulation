<?php
declare(strict_types = 1);

namespace Middlewares;

use Exception;
use Imagecow\Image;
use Interop\Http\Server\MiddlewareInterface;
use Interop\Http\Server\RequestHandlerInterface;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

class ImageManipulation implements MiddlewareInterface
{
    const MAX_FILENAME_LENGTH = 200;
    const DATA_CLAIM = 'im';
    const BASE_PATH = '/_/';

    /**
     * @var string
     */
    private static $currentSignatureKey;

    /**
     * @var string
     */
    private $signatureKey;

    /**
     * @var array|false Enable client hints
     */
    private $clientHints = false;

    /**
     * Build a new uri with the payload.
     */
    public static function getUri(string $path, string $transform, string $signatureKey = null): string
    {
        $signatureKey = $signatureKey ?: self::$currentSignatureKey;

        if ($signatureKey === null) {
            throw new RuntimeException('No signature key provided!'.
                ' You must instantiate the middleware or assign the key as third argument');
        }

        $pos = strrpos($transform, 'format,');

        if ($pos === false) {
            $extension = pathinfo($path, PATHINFO_EXTENSION);
        } else {
            $extension = substr($transform, $pos + 7, 3);
        }

        $token = (new Builder())
            ->set(self::DATA_CLAIM, [$path, $transform])
            ->sign(new Sha256(), $signatureKey)
            ->getToken();

        $token = chunk_split((string) $token, self::MAX_FILENAME_LENGTH, '/');
        $token = str_replace('/.', './', $token);

        return self::BASE_PATH.substr($token, 0, -1).'.'.$extension;
    }

    /**
     * Set the signature key used to encode/decode the data.
     */
    public function __construct(string $signatureKey)
    {
        $this->signatureKey = self::$currentSignatureKey = $signatureKey;
    }

    /**
     * Enable the client hints.
     */
    public function clientHints(array $clientHints = ['Dpr', 'Viewport-Width', 'Width']): self
    {
        $this->clientHints = $clientHints;

        return $this;
    }

    /**
     * Process a request and return a response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strpos($request->getHeaderLine('Accept'), 'image/') === false) {
            return $handler->handle($request);
        }

        $uri = $request->getUri();
        $payload = self::getPayload($uri->getPath());

        if (!$payload) {
            return $handler->handle($request);
        }

        list($path, $transform) = $payload;

        $request = $request->withUri($uri->withPath($path));
        $response = $handler->handle($request);

        if (!empty($this->clientHints)) {
            $response = $response->withHeader('Accept-CH', implode(',', $this->clientHints));
        }

        $size = $response->getBody()->getSize();

        if ($response->getStatusCode() === 200 && ($size === null || $size > 1)) {
            return $this->transform($response, $transform, $this->getClientHints($request));
        }

        return $response;
    }

    /**
     * Transform the image.
     */
    private function transform(ResponseInterface $response, string $transform, array $hints = null): ResponseInterface
    {
        $image = Image::fromString((string) $response->getBody());

        if ($hints) {
            $image->setClientHints($hints);
            $response = $response->withHeader('Vary', implode(', ', $hints));
        }

        $image->transform($transform);

        $body = Utils\Factory::createStream();
        $body->write($image->getString());

        return $response
            ->withBody($body)
            ->withHeader('Content-Type', $image->getMimeType());
    }

    /**
     * Returns the client hints sent.
     *
     * @return array|null
     */
    private function getClientHints(ServerRequestInterface $request)
    {
        if (!empty($this->clientHints)) {
            $hints = [];

            foreach ($this->clientHints as $name) {
                if ($request->hasHeader($name)) {
                    $hints[$name] = $request->getHeaderLine($name);
                }
            }

            return $hints;
        }
    }

    /**
     * Parse and return the payload.
     *
     * @return array|null
     */
    private function getPayload(string $path)
    {
        if (strpos($path, self::BASE_PATH) === false) {
            return;
        }

        try {
            list($basePath, $token) = explode(self::BASE_PATH, $path, 2);

            $token = (new Parser())->parse(str_replace('/', '', substr($token, 0, strrpos($token, '.'))));

            if (!$token->verify(new Sha256(), $this->signatureKey)) {
                return;
            }

            $payload = $token->getClaim(self::DATA_CLAIM);

            if ($payload) {
                $payload[0] = str_replace('//', '/', '/'.$basePath.'/'.$payload[0]);
            }

            return $payload;
        } catch (Exception $exception) {
            return;
        }
    }
}
