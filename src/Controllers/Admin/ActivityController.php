<?php

namespace App\Controllers\Admin;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use PDO;

class ActivityController
{
    private AuthService $authService;
    private PDO $adminDb;
    private Twig $view;

    public function __construct(
        AuthService $authService,
        PDO $adminDb,
        Twig $view
    ) {
        $this->authService = $authService;
        $this->adminDb = $adminDb;
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $params = $request->getQueryParams();

        $page = max(1, (int)($params['page'] ?? 1));
        $limit = 100;
        $offset = ($page - 1) * $limit;

        // Filters
        $where = ['1=1'];
        $queryParams = [];

        if (!empty($params['user_id'])) {
            $where[] = "al.user_id = :user_id";
            $queryParams['user_id'] = $params['user_id'];
        }

        if (!empty($params['action'])) {
            $where[] = "al.action = :action";
            $queryParams['action'] = $params['action'];
        }

        if (!empty($params['entity_type'])) {
            $where[] = "al.entity_type = :entity_type";
            $queryParams['entity_type'] = $params['entity_type'];
        }

        $whereClause = implode(' AND ', $where);

        // Get activity logs
        $sql = "
            SELECT al.*, au.username 
            FROM activity_log al
            LEFT JOIN admin_users au ON al.user_id = au.id
            WHERE {$whereClause}
            ORDER BY al.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->adminDb->prepare($sql);
        foreach ($queryParams as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get total count
        $countSql = "SELECT COUNT(*) FROM activity_log al WHERE {$whereClause}";
        $countStmt = $this->adminDb->prepare($countSql);
        foreach ($queryParams as $key => $value) {
            $countStmt->bindValue(':' . $key, $value);
        }
        $countStmt->execute();
        $totalActivities = $countStmt->fetchColumn();
        $totalPages = ceil($totalActivities / $limit);

        // Get filter options
        $users = $this->adminDb->query("SELECT id, username FROM admin_users ORDER BY username")->fetchAll(PDO::FETCH_ASSOC);
        $actions = $this->adminDb->query("SELECT DISTINCT action FROM activity_log ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
        $entityTypes = $this->adminDb->query("SELECT DISTINCT entity_type FROM activity_log ORDER BY entity_type")->fetchAll(PDO::FETCH_COLUMN);

        return $this->view->render($response, 'admin/activity/index.html.twig', [
            'user' => $user,
            'activities' => $activities,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_activities' => $totalActivities,
            'filters' => $params,
            'users' => $users,
            'actions' => $actions,
            'entity_types' => $entityTypes,
        ]);
    }
}

