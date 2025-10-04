<?php
// src/Services/ImageProxy.php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

class ImageProxy {
    private string $baseUrl;
    private Client $httpClient;
    private string $cacheDir;
    private int $cacheHours;
    private int $maxFileSize;
    private array $allowedDomains;


    public function __construct(array $config) {
        $this->baseUrl = rtrim($config['image_proxy']['base_url'], '/');
        $this->cacheDir = $config['image_cache_dir'];
        $this->cacheHours = $config['cache_hours'];
        $this->maxFileSize = $config['max_file_size'];
        $this->allowedDomains = $config['allowed_domains'];
        $this->httpClient = new Client();

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Main proxy method that handles routing and streaming.
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
        if (!isset($parsedUrl['host']) || !in_array($parsedUrl['host'], $this->allowedDomains)) {
            $response = $response->withStatus(400);
            $response->getBody()->write("Invalid image domain.");
            return $response;
        }

        $hash = md5($externalUrl);
        $cacheFile = $this->cacheDir . '/' . $hash;

        // --- Caching Logic ---
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < ($this->cacheHours * 3600)) {
            return $this->serveCachedImage($response, $cacheFile);
        }

        return $this->fetchAndCacheImage($response, $externalUrl, $cacheFile);
    }

    private function serveCachedImage(Response $response, string $cacheFile): Response {
        $mime = mime_content_type($cacheFile);
        $response = $response->withHeader('Content-Type', $mime)
                             ->withStatus(200);
        $response->getBody()->write(file_get_contents($cacheFile));
        return $response;
    }

    private function fetchAndCacheImage(Response $response, string $url, string $cacheFile): Response {
        try {
            $httpResponse = $this->httpClient->get($url, [
                'verify' => false,
                'sink' => $cacheFile,
                'timeout' => 10,
                'on_headers' => function (\GuzzleHttp\Psr7\Response $response) {
                    $contentLength = $response->getHeaderLine('Content-Length');
                    if ($contentLength > $this->maxFileSize) {
                        throw new \Exception("File too large.");
                    }
                }
            ]);

            // Forward relevant headers and stream file from cache
            $response = $response->withHeader('Content-Type', $httpResponse->getHeaderLine('Content-Type'))
                                 ->withStatus($httpResponse->getStatusCode());

            $response->getBody()->write(file_get_contents($cacheFile));
            return $response;

        } catch (RequestException $e) {
            @unlink($cacheFile);
            $response = $response->withStatus(404);
            $response->getBody()->write("Image not found or could not be fetched: " . $e->getMessage());
            return $response->withHeader('Content-Type', 'text/plain');
        } catch (\Exception $e) {
            @unlink($cacheFile);
            $response = $response->withStatus(400);
            $response->getBody()->write("Error fetching image: " . $e->getMessage());
            return $response->withHeader('Content-Type', 'text/plain');
        }
    }
}
