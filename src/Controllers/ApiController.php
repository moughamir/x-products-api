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
     * @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default="50", maximum="100")),
     * @OA\Parameter(name="fields", in="query", required=false, @OA\Schema(type="string", default="*", example="id,title,price")),
     * @OA\Parameter(name="format", in="query", required=false, @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")),
     * @OA\Response(response=200, description="List of products.", @OA\JsonContent(ref="#/components/schemas/ProductList")),
     * @OA\Response(response="default", description="Error response.")
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
        $products = $this->attachImagesToProducts($products);

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
     * path="/products/search",
     * summary="Performs a Full-Text Search (FTS) for products.",
     * security={{"ApiKeyAuth": {}}},
     * @OA\Parameter(name="q", in="query", required=true, description="Search query string.", @OA\Schema(type="string")),
     * @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default="1")),
     * @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default="50", maximum="100")),
     * @OA\Parameter(name="fields", in="query", required=false, @OA\Schema(type="string", default="*", example="id,title,price")),
     * @OA\Parameter(name="format", in="query", required=false, @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")),
     * @OA\Response(response=200, description="List of products matching the search query.", @OA\JsonContent(ref="#/components/schemas/ProductList")),
     * @OA\Response(response="default", description="Error response.")
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
            $data = ['error' => 'Search query (q) parameter is required.'];
            $response = $response->withStatus(400);
            return $this->outputResponse($response, $data, $format);
        }

        $products = $this->productService->searchProducts($query, $page, $limit, $fieldsParam);
        $products = $this->attachImagesToProducts($products);

        $total = $this->productService->getTotalSearchProducts($query);
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
     * summary="Retrieves a single product by ID or handle (full data).",
     * security={{"ApiKeyAuth": {}}},
     * @OA\Parameter(name="key", in="path", required=true, description="Product ID or Handle.", @OA\Schema(type="string", example="1234567890")),
     * @OA\Parameter(name="format", in="query", required=false, @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")),
     * @OA\Response(response=200, description="The requested product.", @OA\JsonContent(ref="#/components/schemas/Product")),
     * @OA\Response(response=404, description="Product not found."),
     * @OA\Response(response="default", description="Error response.")
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

        $product->images = $this->imageService->getProductImages($product->id);

        return $this->outputResponse($response, ['product' => $product], $format);
    }

    /**
     * @OA\Get(
     * path="/collections/{handle}",
     * summary="Retrieves products from a specific collection handle.",
     * security={{"ApiKeyAuth": {}}},
     * @OA\Parameter(name="handle", in="path", required=true, description="Collection handle (e.g., all, featured, sale).", @OA\Schema(type="string", example="featured")),
     * @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default="1")),
     * @OA\Parameter(name="limit", in="query", required=false, @OA\Schema(type="integer", default="50", maximum="100")),
     * @OA\Parameter(name="fields", in="query", required=false, @OA\Schema(type="string", default="*", example="id,title,price")),
     * @OA\Parameter(name="format", in="query", required=false, @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")),
     * @OA\Response(response=200, description="List of collection products.", @OA\JsonContent(ref="#/components/schemas/ProductList")),
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
     * summary="Image reverse proxy to fetch and serve images from an external CDN.",
     * @OA\Parameter(name="path", in="path", required=true, description="Image path on the external CDN.", @OA\Schema(type="string")),
     * @OA\Response(response=200, description="Image stream."),
     * @OA\Response(response=404, description="Image not found."),
     * )
     */
    public function imageProxy(Request $request, Response $response, array $args): Response
    {
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
