<?php

namespace App\Controllers\Admin;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use PDO;

class DashboardController
{
    private AuthService $authService;
    private Twig $view;
    private PDO $productsDb;
    private PDO $adminDb;

    public function __construct(AuthService $authService, Twig $view, PDO $productsDb, PDO $adminDb)
    {
        $this->authService = $authService;
        $this->view = $view;
        $this->productsDb = $productsDb;
        $this->adminDb = $adminDb;
    }

    /**
     * Show dashboard
     */
    public function index(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();

        // Get statistics
        $stats = $this->getStatistics();

        // Get recent activity
        $recentActivity = $this->getRecentActivity(10);

        return $this->view->render($response, 'admin/dashboard/index.html.twig', [
            'user' => $user,
            'stats' => $stats,
            'recent_activity' => $recentActivity,
        ]);
    }

    /**
     * Get dashboard statistics
     */
    private function getStatistics(): array
    {
        $stats = [];

        // Product statistics
        $stmt = $this->productsDb->query("SELECT COUNT(*) FROM products");
        $stats['total_products'] = $stmt->fetchColumn();

        $stmt = $this->productsDb->query("SELECT COUNT(*) FROM products WHERE in_stock = 1");
        $stats['in_stock_products'] = $stmt->fetchColumn();

        $stmt = $this->productsDb->query("SELECT COUNT(*) FROM products WHERE in_stock = 0");
        $stats['out_of_stock_products'] = $stmt->fetchColumn();

        $stmt = $this->productsDb->query("SELECT COUNT(*) FROM collections");
        $stats['total_collections'] = $stmt->fetchColumn();

        $stmt = $this->productsDb->query("SELECT COUNT(*) FROM collections WHERE is_smart = 1");
        $stats['smart_collections'] = $stmt->fetchColumn();

        $stmt = $this->productsDb->query("SELECT COUNT(*) FROM tags");
        $stats['total_tags'] = $stmt->fetchColumn();

        $stmt = $this->productsDb->query("SELECT COUNT(*) FROM categories");
        $stats['total_categories'] = $stmt->fetchColumn();

        // Calculate average product price
        $stmt = $this->productsDb->query("SELECT AVG(price) FROM products WHERE price > 0");
        $stats['avg_product_price'] = $stmt->fetchColumn() ?: 0;

        // Get total product images count
        $stmt = $this->productsDb->query("SELECT COUNT(*) FROM product_images");
        $stats['total_images'] = $stmt->fetchColumn();

        // Admin statistics
        $stmt = $this->adminDb->query("SELECT COUNT(*) FROM admin_users WHERE is_active = 1");
        $stats['active_users'] = $stmt->fetchColumn();

        $stmt = $this->adminDb->query("SELECT COUNT(*) FROM admin_sessions WHERE expires_at > CURRENT_TIMESTAMP");
        $stats['active_sessions'] = $stmt->fetchColumn();

        $stmt = $this->adminDb->query("SELECT COUNT(*) FROM api_keys WHERE (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)");
        $stats['active_api_keys'] = $stmt->fetchColumn();

        // Get total activity count for today
        $stmt = $this->adminDb->query("SELECT COUNT(*) FROM admin_activity_log WHERE DATE(created_at) = DATE('now')");
        $stats['today_activity_count'] = $stmt->fetchColumn();

        return $stats;
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity(int $limit = 10): array
    {
        $stmt = $this->adminDb->prepare("
            SELECT a.*, u.username, u.full_name
            FROM admin_activity_log a
            JOIN admin_users u ON a.user_id = u.id
            ORDER BY a.created_at DESC
            LIMIT :limit
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

