<?php
// src/Services/ImageProxy.php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
// Psr\Http\Message\StreamFactoryInterface; // Not needed as Guzzle handles streaming
// Psr\Http\Message\ResponseFactoryInterface; // Not strictly needed as we use the passed Response object


class ImageProxy {
    private string $baseUrl;
    private Client $httpClient;
    private array $allowedDomains;
    // Removed unused properties: private ResponseFactoryInterface $responseFactory; private StreamFactoryInterface $streamFactory;

    public function __construct(array $config) {
        // Guzzle is preferred here as it correctly streams and handles headers/errors
        $this->baseUrl = rtrim($config['image_proxy']['base_url'] ?? 'https://cdn.shopify.com', '/');
        // Use allowed domains from config for security, even without caching
        $this->allowedDomains = $config['allowed_domains'] ?? ['cdn.shopify.com'];
        // Disable SSL verification for development environments, remove in production if possible
        $this->httpClient = new Client(['verify' => false]);
    }

    /**
     * Main proxy method that fetches and streams the image directly without caching.
     */
    public function proxy(Request $request, Response $response, array $args): Response
    {
        $path = $args['path'] ?? '';
        $path = ltrim($path, '/');

        // Reconstruct the full external URL
        $externalUrl = $this->baseUrl . '/' . $path;
        $externalUrl .= $request->getUri()->getQuery() ? ('?' . $request->getUri()->getQuery()) : '';

        // Validate URL domain
        $parsedUrl = parse_url($externalUrl);
        if ($parsedUrl === false || !in_array($parsedUrl['host'] ?? '', $this->allowedDomains)) {
            $response = $response->withStatus(400);
            $response->getBody()->write("Invalid image domain.");
            return $response;
        }

        try {
            // Use Guzzle to fetch the image as a stream
            $httpResponse = $this->httpClient->request('GET', $externalUrl, [
                'stream' => true,
                'timeout' => 10,
            ]);

            // Forward relevant headers and status
            $response = $response->withStatus($httpResponse->getStatusCode())
                                 ->withHeader('Content-Type', $httpResponse->getHeaderLine('Content-Type'))
                                 ->withHeader('Cache-Control', 'max-age=2592000, public'); // 30-day client cache

            // Stream the body directly to the PSR-7 response
            $response->getBody()->write($httpResponse->getBody()->getContents());
            return $response;

        } catch (RequestException $e) {
            $response = $response->withStatus(404);
            $response->getBody()->write("Image not found or could not be fetched: " . $e->getMessage());
            return $response->withHeader('Content-Type', 'text/plain');
        } catch (\Exception $e) {
            $response = $response->withStatus(500);
            $response->getBody()->write("Error fetching image: " . $e->getMessage());
            return $response->withHeader('Content-Type', 'text/plain');
        }
    }
}
