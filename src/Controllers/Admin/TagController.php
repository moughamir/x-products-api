<?php

namespace App\Controllers\Admin;

use App\Services\AuthService;
use App\Services\TagService;
use App\Models\Tag;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use PDO;

class TagController
{
    private AuthService $authService;
    private TagService $tagService;
    private PDO $db;
    private Twig $view;

    public function __construct(
        AuthService $authService,
        TagService $tagService,
        PDO $db,
        Twig $view
    ) {
        $this->authService = $authService;
        $this->tagService = $tagService;
        $this->db = $db;
        $this->view = $view;
    }

    public function index(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $params = $request->getQueryParams();

        $page = max(1, (int)($params['page'] ?? 1));
        $limit = 100;

        $tags = Tag::all($this->db, $page, $limit);
        $stats = $this->tagService->getStatistics();

        return $this->view->render($response, 'admin/tags/index.html.twig', [
            'user' => $user,
            'tags' => $tags,
            'stats' => $stats,
            'page' => $page,
            'success' => $_SESSION['success'] ?? null,
            'error' => $_SESSION['error'] ?? null,
        ]);
    }

    public function create(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();

        return $this->view->render($response, 'admin/tags/create.html.twig', [
            'user' => $user,
        ]);
    }

    public function store(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $data = $request->getParsedBody();

        if (empty($data['name'])) {
            $_SESSION['error'] = 'Name is required';
            return $response->withHeader('Location', '/cosmos/admin/tags/new')->withStatus(302);
        }

        try {
            $tag = $this->tagService->findOrCreate($data['name']);
            $this->authService->logActivity($user['id'], 'create', 'tag', "Created tag: {$tag->name}");
            $_SESSION['success'] = "Tag '{$tag->name}' created successfully";
            return $response->withHeader('Location', '/cosmos/admin/tags')->withStatus(302);
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to create tag: ' . $e->getMessage();
            return $response->withHeader('Location', '/cosmos/admin/tags/new')->withStatus(302);
        }
    }

    public function edit(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $tagId = (int)$args['id'];

        $tag = Tag::find($this->db, $tagId);
        if (!$tag) {
            $_SESSION['error'] = 'Tag not found';
            return $response->withHeader('Location', '/cosmos/admin/tags')->withStatus(302);
        }

        return $this->view->render($response, 'admin/tags/edit.html.twig', [
            'user' => $user,
            'tag' => $tag,
        ]);
    }

    public function update(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $tagId = (int)$args['id'];
        $data = $request->getParsedBody();

        $tag = Tag::find($this->db, $tagId);
        if (!$tag) {
            $_SESSION['error'] = 'Tag not found';
            return $response->withHeader('Location', '/cosmos/admin/tags')->withStatus(302);
        }

        if (empty($data['name'])) {
            $_SESSION['error'] = 'Name is required';
            return $response->withHeader('Location', "/cosmos/admin/tags/{$tagId}/edit")->withStatus(302);
        }

        try {
            $tag->name = $data['name'];
            $tag->slug = $this->tagService->generateUniqueSlug($data['name'], $tagId);
            $tag->save($this->db);

            $this->authService->logActivity($user['id'], 'update', 'tag', "Updated tag: {$tag->name}");
            $_SESSION['success'] = "Tag '{$tag->name}' updated successfully";
            return $response->withHeader('Location', '/cosmos/admin/tags')->withStatus(302);
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to update tag: ' . $e->getMessage();
            return $response->withHeader('Location', "/cosmos/admin/tags/{$tagId}/edit")->withStatus(302);
        }
    }

    public function delete(Request $request, Response $response, array $args): Response
    {
        $user = $this->authService->getUserFromSession();
        $tagId = (int)$args['id'];

        $tag = Tag::find($this->db, $tagId);
        if (!$tag) {
            $_SESSION['error'] = 'Tag not found';
            return $response->withHeader('Location', '/cosmos/admin/tags')->withStatus(302);
        }

        try {
            $name = $tag->name;
            $tag->delete($this->db);
            $this->authService->logActivity($user['id'], 'delete', 'tag', "Deleted tag: {$name}");
            $_SESSION['success'] = "Tag deleted successfully";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete tag: ' . $e->getMessage();
        }

        return $response->withHeader('Location', '/cosmos/admin/tags')->withStatus(302);
    }

    public function bulkDelete(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();
        $data = $request->getParsedBody();
        $tagIds = array_map('intval', explode(',', $data['tag_ids'] ?? ''));

        if (empty($tagIds)) {
            $_SESSION['error'] = 'No tags selected';
            return $response->withHeader('Location', '/cosmos/admin/tags')->withStatus(302);
        }

        try {
            $count = $this->tagService->bulkDelete($tagIds);
            $this->authService->logActivity($user['id'], 'delete', 'tag', "Bulk deleted {$count} tags");
            $_SESSION['success'] = "Successfully deleted {$count} tags";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to delete tags: ' . $e->getMessage();
        }

        return $response->withHeader('Location', '/cosmos/admin/tags')->withStatus(302);
    }

    public function cleanupUnused(Request $request, Response $response): Response
    {
        $user = $this->authService->getUserFromSession();

        try {
            $count = $this->tagService->deleteUnusedTags();
            $this->authService->logActivity($user['id'], 'delete', 'tag', "Cleaned up {$count} unused tags");
            $_SESSION['success'] = "Successfully deleted {$count} unused tags";
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Failed to cleanup tags: ' . $e->getMessage();
        }

        return $response->withHeader('Location', '/cosmos/admin/tags')->withStatus(302);
    }
}

