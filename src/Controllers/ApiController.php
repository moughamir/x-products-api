<?php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Models\Product;
use App\Services\ImageService;
use App\Services\ProductService;
use App\Models\MsgPackResponse;
use Slim\Views\Twig;
use PDO;

use OpenApi\Annotations as OA;
use OpenApi\Generator;


class ApiController
{
    private ProductService $productService;
    private ImageService $imageService;
    private Twig $view;
    private string $sourceDir;

    public function __construct(ProductService $productService, ImageService $imageService, Twig $view, string $sourceDir)
    {
        $this->productService = $productService;
        $this->imageService = $imageService;
        $this->view = $view;
        $this->sourceDir = $sourceDir;
    }

    private function outputResponse(Response $response, array $data, string $format): Response
    {
        // Validate format against allowed formats from config
        $config = require __DIR__ . '/../../config/app.php';
        $allowedFormats = $config['response_formats']['available'] ?? ['json', 'msgpack'];
        $defaultFormat = $config['response_formats']['default'] ?? 'json';

        // If format is not supported, fall back to default
        if (!in_array($format, $allowedFormats)) {
            $format = $defaultFormat;
        }

        // Convert any Product objects to arrays with proper string IDs
        if (isset($data['products']) && is_array($data['products'])) {
            $data['products'] = array_map(function($product) {
                return $product instanceof \App\Models\Product ? $product->toArray() : $product;
            }, $data['products']);
        } elseif (isset($data['id']) && !isset($data['products'])) {
            // Single product response
            if ($data instanceof \App\Models\Product) {
                $data = $data->toArray();
            }
        }

        if ($format === 'msgpack') {
            try {
                // Use the static method from MsgPackResponse
                return MsgPackResponse::withMsgPack($response, $data);
            } catch (\Exception $e) {
                // Log the error (in a production environment)
                error_log('MsgPack error: ' . $e->getMessage());

                // Fallback to JSON with an error message
                $errorData = [
                    'error' => 'MessagePack format not available: ' . $e->getMessage(),
                    'data' => $data
                ];
                $response->getBody()->write(json_encode($errorData));
                return $response
                    ->withHeader('Content-Type', 'application/json')
                    ->withStatus(500);
            }
        }

        // Default JSON response
        $jsonOptions = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $response->getBody()->write(json_encode($data, $jsonOptions));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @OA\Get(
     * path="/products",
     * summary="Retrieves a paginated list of all products (limited fields).",
     * @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default="1")),
     * @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default="50", maximum="100")),
     * @OA\Parameter(name="fields", in="query", required=false, description="Comma-separated list of fields to return (e.g., id,title,price). Default is all fields.", @OA\Schema(type="string", default="*")),
     * @OA\Parameter(name="format", in="query", required=false, @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")),
     * @OA\Response(response=200, description="A paginated list of products.", @OA\JsonContent(ref="#/components/schemas/ProductList")),
     * security={{"ApiKeyAuth": {}}}
     * )
     */
    public function getProducts(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));
        $fieldsParam = $params['fields'] ?? '*';
        $format = $params['format'] ?? 'json';

        $products = $this->productService->getProducts($page, $limit, $fieldsParam);
        $total = $this->productService->getTotalProducts();

        // Only attach images if all fields are requested
        if ($fieldsParam === '*') {
            $products = $this->attachImagesToProducts($products);
        }

        $data = [
            'products' => $products,
            'meta' => [
                'total' => (int) $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ];

        return $this->outputResponse($response, $data, $format);
    }

    /**
     * @OA\Get(
     * path="/products/{key}",
     * summary="Retrieves a single product by ID or handle.",
     * @OA\Parameter(name="key", in="path", required=true, description="Product ID (int) or Handle (string)", @OA\Schema(type="string", example="example-product")),
     * @OA\Parameter(name="format", in="query", required=false, @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")),
     * @OA\Response(response=200, description="The full product object.", @OA\JsonContent(ref="#/components/schemas/Product")),
     * @OA\Response(response=404, description="Product not found."),
     * security={{"ApiKeyAuth": {}}}
     * )
     */
    public function getProductOrHandle(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'];
        $format = $request->getQueryParams()['format'] ?? 'json';

        $product = $this->productService->getProductOrHandle($key);

        if (!$product) {
            $data = ['error' => 'Product not found.'];
            return $this->outputResponse($response->withStatus(404), $data, $format);
        }

        // Attach images, variants, and options
        $products = $this->attachImagesToProducts([$product]);
        $data = $products[0]->toArray();

        return $this->outputResponse($response, $data, $format);
    }

    /**
     * @OA\Get(
     * path="/products/search",
     * summary="Performs a full-text search across product titles and descriptions.",
     * @OA\Parameter(name="q", in="query", required=true, description="Search query string.", @OA\Schema(type="string", example="blue shirt")),
     * @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default="1")),
     * @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default="50", maximum="100")),
     * @OA\Parameter(name="fields", in="query", required=false, description="Comma-separated list of fields to return.", @OA\Schema(type="string", default="*")),
     * @OA\Parameter(name="format", in="query", required=false, @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")),
     * @OA\Response(response=200, description="A paginated list of products matching the query.", @OA\JsonContent(ref="#/components/schemas/ProductList")),
     * security={{"ApiKeyAuth": {}}}
     * )
     */
    public function searchProducts(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $query = $params['q'] ?? '';
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));
        $fieldsParam = $params['fields'] ?? '*';
        $format = $params['format'] ?? 'json';

        if (empty($query)) {
            $data = ['error' => 'Search query `q` cannot be empty.'];
            return $this->outputResponse($response->withStatus(400), $data, $format);
        }

        $products = $this->productService->searchProducts($query, $page, $limit, $fieldsParam);
        $total = $this->productService->getTotalSearchProducts($query);

        if ($fieldsParam === '*') {
            $products = $this->attachImagesToProducts($products);
        }

        $data = [
            'products' => $products,
            'meta' => [
                'total' => (int) $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => ceil($total / $limit)
            ]
        ];

        return $this->outputResponse($response, $data, $format);
    }

    /**
     * @OA\Get(
     * path="/collections/{handle}",
     * summary="Retrieves a list of products by collection handle.",
     * @OA\Parameter(name="handle", in="path", required=true, description="Collection handle (e.g., all, featured, sale)", @OA\Schema(type="string", example="featured")),
     * @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default="1")),
     * @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default="50", maximum="100")),
     * @OA\Parameter(name="fields", in="query", required=false, description="Comma-separated list of fields to return.", @OA\Schema(type="string", default="*")),
     * @OA\Parameter(name="format", in="query", required=false, @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")),
     * @OA\Response(response=200, description="A list of products belonging to the collection.", @OA\JsonContent(ref="#/components/schemas/ProductList")),
     * @OA\Response(response=404, description="Collection handle is not supported.")
     * )
     */
    public function getCollectionProducts(Request $request, Response $response, array $args): Response
    {
        $collectionHandle = $args['handle'];
        $params = $request->getQueryParams();
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));
        $fieldsParam = $params['fields'] ?? '*';
        $format = $params['format'] ?? 'json';

        $products = $this->productService->getCollectionProducts($collectionHandle, $page, $limit, $fieldsParam);

        if (empty($products) && $this->productService->getTotalCollectionProducts($collectionHandle) === 0) {
            $data = ['error' => 'Invalid collection handle or no products found.'];
            $response = $response->withStatus(404);
            return $this->outputResponse($response, $data, $format);
        }

        if ($fieldsParam === '*') {
            $products = $this->attachImagesToProducts($products);
        }

        // Metadata handling based on whether the collection is paginated (e.g., 'featured' is not)
        $data = ['products' => $products];

        $total = $this->productService->getTotalCollectionProducts($collectionHandle);
        $data['meta'] = [
            'total' => (int) $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($total / $limit)
        ];

        return $this->outputResponse($response, $data, $format);
    }

    /**
     * @OA\Get(
     * path="/cdn/{path:.*}",
     * summary="Reverse proxy for external images (CDN).",
     * description="Streams an image from the configured external CDN (`https://cdn.shopify.com`) via this domain.",
     * @OA\Parameter(name="path", in="path", required=true, description="The path to the image on the external CDN.", @OA\Schema(type="string", example="s/files/1/0000/0000/products/image.jpg")),
     * @OA\Response(response=200, description="The streamed image content."),
     * @OA\Response(response=400, description="Invalid image domain."),
     * @OA\Response(response=404, description="Image not found."),
     * )
     */
    public function imageProxy(Request $request, Response $response, array $args): Response
    {
        // This assumes ImageProxy is accessible via the ProductService or another mechanism
        $imageProxyService = $this->productService->getImageProxyService();
        return $imageProxyService->proxy($request, $response, $args);
    }

    public function swaggerUi(Request $request, Response $response, array $args): Response
    {
        // Get the base URL for the API
        $uri = $request->getUri();
        $baseUrl = $uri->getScheme() . '://' . $uri->getHost();
        if ($uri->getPort()) {
            $baseUrl .= ':' . $uri->getPort();
        }
        $baseUrl .= '/cosmos';

        return $this->view->render($response, 'swagger.html', [
            'pageTitle' => 'Cosmos API Documentation',
            'openapi_spec_url' => $baseUrl . '/openapi.json',
            'api_key' => '' // Leave empty for security, user will input their key
        ]);
    }

    public function swaggerJson(Request $request, Response $response, array $args): Response
    {
        // Ensure BASE_PATH exists before config bootstrap
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 2)); // Points to project root
        }

        $openapi = Generator::scan([$this->sourceDir, __DIR__ . '/../OpenApi.php']);

        $response->getBody()->write($openapi->toJson());
        return $response->withHeader('Content-Type', 'application/json');
    }
    private function attachImagesToProducts(array $products): array
    {
        if (empty($products)) {
            return [];
        }

        $productIds = array_map(fn($p) => $p->id, $products);
        $images = $this->imageService->getImagesForProducts($productIds);

        $imagesByProduct = [];
        foreach ($images as $image) {
            $imagesByProduct[$image->product_id][] = $image;
        }

        foreach ($products as $product) {
            $product->images = $imagesByProduct[$product->id] ?? [];

            // Decode and attach variants
            $this->attachVariantsToProduct($product);

            // Decode and attach options
            $this->attachOptionsToProduct($product);
        }

        return $products;
    }

    /**
     * Decode variants_json and attach to product with proper formatting
     */
    private function attachVariantsToProduct(\App\Models\Product $product): void
    {
        if (empty($product->variants_json)) {
            $product->variants = [];
            return;
        }

        $variants = json_decode($product->variants_json, true);
        if (!is_array($variants)) {
            $product->variants = [];
            return;
        }

        // Format each variant according to the API specification
        $product->variants = array_map(function($variant) use ($product) {
            // Format featured_image if present
            $featuredImage = null;
            if (isset($variant['featured_image']) && is_array($variant['featured_image'])) {
                $img = $variant['featured_image'];
                $featuredImage = [
                    'id' => (string)($img['id'] ?? ''),
                    'product_id' => (string)($img['product_id'] ?? $product->id),
                    'position' => (int)($img['position'] ?? 1),
                    'created_at' => $img['created_at'] ?? '',
                    'updated_at' => $img['updated_at'] ?? '',
                    'alt' => $img['alt'] ?? null,
                    'width' => isset($img['width']) ? (int)$img['width'] : null,
                    'height' => isset($img['height']) ? (int)$img['height'] : null,
                    'src' => $img['src'] ?? '',
                    'variant_ids' => isset($img['variant_ids']) && is_array($img['variant_ids'])
                        ? array_map('strval', $img['variant_ids'])
                        : []
                ];
            }

            return [
                'id' => (string)($variant['id'] ?? ''),
                'product_id' => (string)$product->id,
                'title' => $variant['title'] ?? '',
                'option1' => $variant['option1'] ?? null,
                'option2' => $variant['option2'] ?? null,
                'option3' => $variant['option3'] ?? null,
                'sku' => $variant['sku'] ?? null,
                'requires_shipping' => (bool)($variant['requires_shipping'] ?? true),
                'taxable' => (bool)($variant['taxable'] ?? true),
                'featured_image' => $featuredImage,
                'available' => (bool)($variant['available'] ?? false),
                'price' => (float)($variant['price'] ?? 0),
                'grams' => (int)($variant['grams'] ?? 0),
                'compare_at_price' => isset($variant['compare_at_price']) && $variant['compare_at_price'] !== null
                    ? (float)$variant['compare_at_price']
                    : null,
                'position' => (int)($variant['position'] ?? 1),
                'created_at' => $variant['created_at'] ?? '',
                'updated_at' => $variant['updated_at'] ?? ''
            ];
        }, $variants);
    }

    /**
     * Decode options_json and attach to product with proper formatting
     */
    private function attachOptionsToProduct(\App\Models\Product $product): void
    {
        if (empty($product->options_json)) {
            $product->options = [];
            return;
        }

        $options = json_decode($product->options_json, true);
        if (!is_array($options)) {
            $product->options = [];
            return;
        }

        // Format each option according to the API specification
        $product->options = array_map(function($option, $index) use ($product) {
            // Generate a unique ID if not present: product_id + position
            $position = (int)($option['position'] ?? $index + 1);
            $optionId = isset($option['id']) ? (string)$option['id'] : (string)($product->id . $position);

            return [
                'id' => $optionId,
                'product_id' => (string)$product->id,
                'name' => $option['name'] ?? '',
                'position' => $position,
                'values' => is_array($option['values'] ?? null) ? $option['values'] : []
            ];
        }, $options, array_keys($options));
    }
}
