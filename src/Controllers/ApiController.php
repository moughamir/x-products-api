<?php
namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Models\Product;
use App\Services\ImageService;
use App\Services\ProductService;
use Slim\Views\Twig;
use PDO;

use OpenApi\Annotations as OA;
use OpenApi\Generator; // Crucial for dynamic generation

/**
 * @OA\OpenApi(
 * @OA\Info(
 * version="1.0.0",
 * title="Cosmos Product API",
 * description="The final source for product data, search, and images. Authentication requires an **X-API-KEY** header.",
 * @OA\License(name="MIT")
 * ),
 * @OA\Server(
 * url="/cosmos",
 * description="API Base Path"
 * )
 * )
 * @OA\SecurityScheme(
 * securityScheme="ApiKeyAuth",
 * type="apiKey",
 * in="header",
 * name="X-API-KEY"
 * )
 * * * --- OpenAPI Schemas for Data Models ---
 * @OA\Schema(
 * schema="Image",
 * type="object",
 * title="Image",
 * @OA\Property(property="id", type="integer", example=9876),
 * @OA\Property(property="product_id", type="integer", example=12345),
 * @OA\Property(property="position", type="integer", example=1),
 * @OA\Property(property="src", type="string", format="url", example="http://example.com/image.jpg"),
 * @OA\Property(property="width", type="integer", example=1024),
 * @OA\Property(property="height", type="integer", example=768)
 * )
 * @OA\Schema(
 * schema="Product",
 * type="object",
 * title="Product",
 * @OA\Property(property="id", type="integer", example=12345678),
 * @OA\Property(property="title", type="string", example="Red Leather Jacket"),
 * @OA\Property(property="handle", type="string", example="red-leather-jacket"),
 * @OA\Property(property="price", type="number", format="float", example=129.99),
 * @OA\Property(property="in_stock", type="integer", example=1, description="1 for true, 0 for false"),
 * @OA\Property(
 * property="images",
 * type="array",
 * description="List of associated image objects.",
 * @OA\Items(ref="#/components/schemas/Image")
 * )
 * )
 * @OA\Schema(
 * schema="PaginatedProductList",
 * type="object",
 * title="Paginated Product List",
 * @OA\Property(
 * property="products",
 * type="array",
 * @OA\Items(ref="#/components/schemas/Product")
 * ),
 * @OA\Property(
 * property="meta",
 * type="object",
 * description="Pagination metadata (only included on list endpoints)",
 * @OA\Property(property="total", type="integer", example=1000),
 * @OA\Property(property="page", type="integer", example=1),
 * @OA\Property(property="limit", type="integer", example=50),
 * @OA\Property(property="total_pages", type="integer", example=20)
 * )
 * )
 */
class ApiController
{
    private ProductService $productService;
    private ImageService $imageService;
    private Twig $twig;

    public function __construct(ProductService $productService, ImageService $imageService, Twig $twig)
    {
        $this->productService = $productService;
        $this->imageService = $imageService;
        $this->twig = $twig;
    }

    /**
     * Helper to output response in JSON or MessagePack.
     */
    private function outputResponse(Response $response, array $data, string $format = 'json'): Response
    {
        if ($format === 'msgpack' && extension_loaded('msgpack')) {
            $packedData = msgpack_pack($data);
            $response->getBody()->write($packedData);
            return $response->withHeader('Content-Type', 'application/x-msgpack');
        }

        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Helper to fetch and map images to a list of products efficiently (N+1 fix).
     * @param array $products
     */
    private function attachImagesToProducts(array $products): array
    {
        if (empty($products)) {
            return [];
        }

        $productIds = array_map(fn($p) => $p->id, $products);
        $allImages = $this->imageService->getImagesForProducts($productIds);

        $imagesByProductId = [];
        foreach ($allImages as $img) {
            $imagesByProductId[$img->product_id][] = $img;
        }

        foreach ($products as $product) {
            $product->images = $imagesByProductId[$product->id] ?? [];
        }

        return $products;
    }


    // --- DOCUMENTATION METHODS ---

    /**
     * @OA\Get(
     * path="/swagger-ui",
     * summary="Interactive API Documentation",
     * tags={"Documentation"},
     * security={},
     * @OA\Response(response=200, description="Renders the Swagger UI.")
     * )
     */
    public function swaggerUi(Request $request, Response $response): Response
    {
        return $this->twig->render($response, 'swagger.html', [
            // This URL tells the Swagger UI where to fetch the specification from.
            'openapi_spec_url' => '/cosmos/openapi.json'
        ]);
    }

    /**
     * Dynamically generates and serves the OpenAPI specification JSON.
     * This is the function responsible for serving the 'openapi.json' content.
     * * @OA\Get(
     * path="/openapi.json",
     * summary="Raw OpenAPI Specification",
     * tags={"Documentation"},
     * security={},
     * @OA\Response(response=200, description="Raw JSON specification file.")
     * )
     */
    public function swaggerJson(Request $request, Response $response): Response
    {
        try {
            // Get the container to access 'source_dir' configuration (set in App.php)
            $container = $request->getAttribute('container');
            if (!$container || !$container->has('source_dir')) {
                 // Fallback error if DI is misconfigured
                 throw new \Exception("Container or 'source_dir' not available. Check App.php setup.");
            }
            $sourceDir = $container->get('source_dir');

            // Generator scans the source directory (Controller folder) for annotations
            $openapi = Generator::scan([$sourceDir]);

            // Write the generated JSON to the response body
            $response->getBody()->write($openapi->toJson());

            // Set the correct Content-Type header
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            // Handle generation error gracefully
            $response = $response->withStatus(500);
            $response->getBody()->write(json_encode(['error' => 'Failed to generate OpenAPI spec: ' . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json');
        }
    }


    // --- API ENDPOINTS (Annotated) ---

    /**
     * @OA\Get(
     * path="/products",
     * summary="List and paginate all products",
     * tags={"Products"},
     * security={{"ApiKeyAuth": {}}},
     * @OA\Parameter(
     * name="page",
     * in="query",
     * required=false,
     * description="Page number (default: 1)",
     * @OA\Schema(type="integer", default=1)
     * ),
     * @OA\Parameter(
     * name="limit",
     * in="query",
     * required=false,
     * description="Items per page (max 100, default: 50)",
     * @OA\Schema(type="integer", default=50)
     * ),
     * @OA\Parameter(
     * name="fields",
     * in="query",
     * required=false,
     * description="Comma-separated list of fields to return (e.g., id, title, price). Defaults to all fields.",
     * @OA\Schema(type="string", default="*")
     * ),
     * @OA\Parameter(
     * name="format",
     * in="query",
     * required=false,
     * description="Response format.",
     * @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")
     * ),
     * @OA\Response(
     * response=200,
     * description="List of products and pagination metadata.",
     * @OA\JsonContent(ref="#/components/schemas/PaginatedProductList")
     * )
     * )
     */
    public function getProducts(Request $request, Response $response): Response
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
     * path="/products/{key}",
     * summary="Retrieve a single product by ID or Handle",
     * tags={"Products"},
     * security={{"ApiKeyAuth": {}}},
     * @OA\Parameter(
     * name="key",
     * in="path",
     * required=true,
     * description="Product ID (numeric) or Handle (slug string).",
     * @OA\Schema(type="string", example="12345678")
     * ),
     * @OA\Parameter(
     * name="format",
     * in="query",
     * required=false,
     * description="Response format.",
     * @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")
     * ),
     * @OA\Response(
     * response=200,
     * description="Product found.",
     * @OA\JsonContent(
     * @OA\Property(property="product", ref="#/components/schemas/Product")
     * )
     * ),
     * @OA\Response(response=404, description="Product not found.")
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
        } else {
            // Fetch images for a single product
            $product->images = $this->imageService->getProductImages($product->id);
            $data = ['product' => $product];
        }

        return $this->outputResponse($response, $data, $format);
    }

    /**
     * @OA\Get(
     * path="/products/search",
     * summary="Search products using keywords (Full-Text Search)",
     * tags={"Products"},
     * security={{"ApiKeyAuth": {}}},
     * @OA\Parameter(
     * name="q",
     * in="query",
     * required=true,
     * description="Search query string (uses FTS5 syntax).",
     * @OA\Schema(type="string", example="red shoes")
     * ),
     * @OA\Parameter(
     * name="format",
     * in="query",
     * required=false,
     * description="Response format.",
     * @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")
     * ),
     * @OA\Response(
     * response=200,
     * description="List of products matching the search query.",
     * @OA\JsonContent(
     * @OA\Property(
     * property="products",
     * type="array",
     * @OA\Items(ref="#/components/schemas/Product")
     * )
     * )
     * )
     */
    public function searchProducts(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $query = $params['q'] ?? null;
        $fieldsParam = $params['fields'] ?? '*';
        $format = $params['format'] ?? 'json';

        if (empty($query)) {
            $data = ['error' => 'Missing required parameter: q (query).'];
            $response = $response->withStatus(400);
            return $this->outputResponse($response, $data, $format);
        }

        $products = $this->productService->searchProducts($query, $fieldsParam);
        $products = $this->attachImagesToProducts($products);

        $data = [
            'products' => $products,
            'meta' => ['count' => count($products)]
        ];

        return $this->outputResponse($response, $data, $format);
    }

    /**
     * @OA\Get(
     * path="/collections/{handle}",
     * summary="Retrieve products belonging to a specific collection type",
     * tags={"Collections"},
     * security={{"ApiKeyAuth": {}}},
     * @OA\Parameter(
     * name="handle",
     * in="path",
     * required=true,
     * description="Collection slug. Options: all, featured, sale, new, bestsellers, trending.",
     * @OA\Schema(type="string", enum={"all", "featured", "sale", "new", "bestsellers", "trending"}, example="sale")
     * ),
     * @OA\Parameter(
     * name="page",
     * in="query",
     * required=false,
     * description="Page number (default: 1)",
     * @OA\Schema(type="integer", default=1)
     * ),
     * @OA\Parameter(
     * name="limit",
     * in="query",
     * required=false,
     * description="Items per page (max 100, default: 50)",
     * @OA\Schema(type="integer", default=50)
     * ),
     * @OA\Parameter(
     * name="format",
     * in="query",
     * required=false,
     * description="Response format.",
     * @OA\Schema(type="string", enum={"json", "msgpack"}, default="json")
     * ),
     * @OA\Response(
     * response=200,
     * description="List of products in the specified collection.",
     * @OA\JsonContent(ref="#/components/schemas/PaginatedProductList")
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
}
