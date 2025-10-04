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
        if ($format === 'msgpack') {
            return new MsgPackResponse($response, $data);
        }

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @OA\Get(
     * path="/products",
     * summary="Retrieves a paginated list of all products (limited fields).",
     * @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default="1")),
     * @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default="50")),
     * @OA\Parameter(name="fields", in="query", required=false, description="Comma-separated fields to return (e.g., id,title,price). '*' for all fields.", @OA\Schema(type="string", default="*")),
     * @OA\Parameter(name="format", in="query", required=false, description="Output format.", @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")),
     * @OA\Response(
     * response=200,
     * description="A list of products with pagination metadata.",
     * @OA\JsonContent(ref="#/components/schemas/ProductList")
     * ),
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
     * @OA\Parameter(name="key", in="path", required=true, description="Product ID (int) or handle (string).", @OA\Schema(type="string")),
     * @OA\Parameter(name="format", in="query", required=false, description="Output format.", @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")),
     * @OA\Response(
     * response=200,
     * description="A single product object, including all variants, images, and options.",
     * @OA\JsonContent(ref="#/components/schemas/Product")
     * ),
     * @OA\Response(response=404, description="Product not found."),
     * security={{"ApiKeyAuth": {}}}
     * )
     */
    public function getProductOrHandle(Request $request, Response $response, array $args): Response
    {
        $key = $args['key'];
        $params = $request->getQueryParams();
        $format = $params['format'] ?? 'json';

        $product = $this->productService->getProductOrHandle($key);

        if (!$product) {
            $data = ['error' => 'Product not found.'];
            $response = $response->withStatus(404);
            return $this->outputResponse($response, $data, $format);
        }

        $product = $this->attachImagesToProducts([$product])[0];
        $data = ['product' => $product];

        return $this->outputResponse($response, $data, $format);
    }

    /**
     * @OA\Get(
     * path="/products/search",
     * summary="Performs a full-text search across products.",
     * @OA\Parameter(name="q", in="query", required=true, description="The search query string.", @OA\Schema(type="string")),
     * @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default="1")),
     * @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default="50")),
     * @OA\Parameter(name="format", in="query", required=false, description="Output format.", @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")),
     * @OA\Response(
     * response=200,
     * description="A paginated list of products matching the search query.",
     * @OA\JsonContent(ref="#/components/schemas/ProductList")
     * ),
     * security={{"ApiKeyAuth": {}}}
     * )
     */
    public function searchProducts(Request $request, Response $response, array $args): Response
    {
        $params = $request->getQueryParams();
        $query = $params['q'] ?? '';
        $page = max(1, (int) ($params['page'] ?? 1));
        $limit = min(100, max(1, (int) ($params['limit'] ?? 50)));
        $format = $params['format'] ?? 'json';

        if (empty($query)) {
            $data = ['error' => 'Search query (q) parameter is required.'];
            $response = $response->withStatus(400);
            return $this->outputResponse($response, $data, $format);
        }

        $result = $this->productService->searchProducts($query, $page, $limit);
        $products = $result['products'];
        $total = $result['total'];

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
     * summary="Retrieves products for a specific collection handle (e.g., featured, sale).",
     * @OA\Parameter(name="handle", in="path", required=true, description="The collection handle (e.g., 'all', 'featured', 'sale').", @OA\Schema(type="string")),
     * @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default="1")),
     * @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default="50")),
     * @OA\Parameter(name="fields", in="query", required=false, description="Comma-separated fields to return (e.g., id,title,price). '*' for all fields.", @OA\Schema(type="string", default="*")),
     * @OA\Parameter(name="format", in="query", required=false, description="Output format.", @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")),
     * @OA\Response(
     * response=200,
     * description="A paginated list of products in the specified collection.",
     * @OA\JsonContent(ref="#/components/schemas/ProductList")
     * ),
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

        $products = $this->attachImagesToProducts($products);

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
     * path="/cdn/{path}",
     * summary="Reverse proxy for external images.",
     * description="Proxies images from the external CDN (e.g., cdn.shopify.com).",
     * @OA\Parameter(name="path", in="path", required=true, description="The URL path segment of the image to fetch.", @OA\Schema(type="string")),
     * @OA\Response(response=200, description="Image streamed successfully.", @OA\MediaType(mediaType="image/*", @OA\Schema(type="string", format="binary"))),
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
        return $this->view->render($response, 'swagger.html', [
            'pageTitle' => 'Cosmos API Documentation'
        ]);
    }

    public function swaggerJson(Request $request, Response $response, array $args): Response
    {
        $openapi = Generator::scan([$this->sourceDir]);

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
        }

        return $products;
    }
}
