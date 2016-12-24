<?php

namespace Middlewares;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Interop\Http\ServerMiddleware\DelegateInterface;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Imagecow\Image;
use RuntimeException;
use Exception;

class ImageManipulation implements MiddlewareInterface
{
    const DATA_CLAIM = 'im';

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
     *
     * @param string      $path         The image path
     * @param string      $transform    The image transform
     * @param string|null $signatureKey
     *
     * @return string
     */
    public static function getUri($path, $transform, $signatureKey = null)
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

        return '/'.str_replace('.', '/', $token).'.'.$extension;
    }

    /**
     * Set the signature key used to encode/decode the data.
     *
     * @param string $signatureKey
     */
    public function __construct($signatureKey)
    {
        $this->signatureKey = self::$currentSignatureKey = $signatureKey;
    }

    /**
     * Enable the client hints.
     *
     * @param array $clientHints
     *
     * @return self
     */
    public function clientHints($clientHints = ['Dpr', 'Viewport-Width', 'Width'])
    {
        $this->clientHints = $clientHints;

        return $this;
    }

    /**
     * Process a request and return a response.
     *
     * @param ServerRequestInterface $request
     * @param DelegateInterface      $delegate
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        if (strpos($request->getHeaderLine('Accept'), 'image/') === false) {
            return $delegate->process($request);
        }

        $uri = $request->getUri();
        $payload = self::getPayload($uri->getPath());

        if (!$payload) {
            return $delegate->process($request);
        }

        list($path, $transform) = $payload;

        $request = $request->withUri($uri->withPath($path));
        $response = $delegate->process($request);

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
     *
     * @param ResponseInterface $response
     * @param string            $transform
     * @param array|null        $hints
     *
     * @return ResponseInterface
     */
    private function transform(ResponseInterface $response, $transform, array $hints = null)
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
     * @param ServerRequestInterface $request
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
     * @param string $path
     *
     * @return array|null
     */
    private function getPayload($path)
    {
        try {
            $path = explode('/', trim($path, '/'));

            if (count($path) < 3) {
                return;
            }

            $token = array_splice($path, -3);
            $token[2] = pathinfo($token[2], PATHINFO_FILENAME);
            $token = (new Parser())->parse(implode('.', $token));

            if (!$token->verify(new Sha256(), $this->signatureKey)) {
                return;
            }

            $payload = $token->getClaim(self::DATA_CLAIM);

            if (!empty($path) && $payload) {
                $payload[0] = str_replace('//', '/', '/'.implode('/', $path).'/'.$payload[0]);
            }

            return $payload;
        } catch (Exception $exception) {
            //silenced error
        }
    }
}
