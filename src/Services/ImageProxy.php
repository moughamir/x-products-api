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
        // Get base URL from config with fallback
        $this->baseUrl = rtrim($config['image_proxy']['base_url'] ?? 'https://cdn.shopify.com', '/');

        // Use allowed domains from config for security
        $this->allowedDomains = $config['allowed_domains'] ?? ['cdn.shopify.com'];

        // Create Guzzle client with proper configuration
        $this->httpClient = new Client([
            // Enable SSL verification for security (important for production)
            'verify' => true,
            // Set reasonable timeout values
            'connect_timeout' => 3,
            'timeout' => 10,
            // Follow redirects but limit to prevent redirect loops
            'allow_redirects' => [
                'max' => 5,
                'strict' => true,
                'referer' => true,
            ],
            // Set user agent
            'headers' => [
                'User-Agent' => 'CosmosAPI/1.0 ImageProxy'
            ]
        ]);
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

            // Stream the body in chunks to avoid loading the entire image into memory
            $body = $httpResponse->getBody();
            $responseBody = $response->getBody();

            // Read in 4KB chunks and write to the response
            while (!$body->eof()) {
                $responseBody->write($body->read(4096));
            }

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
