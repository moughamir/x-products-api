<?php

namespace App\Services;

use App\Models\AdminUser;
use PDO;

class AuthService
{
    private PDO $db;
    private AdminUser $userModel;
    private array $config;

    public function __construct(PDO $db, array $config)
    {
        $this->db = $db;
        $this->userModel = new AdminUser($db);
        $this->config = $config;
    }

    /**
     * Attempt to authenticate a user
     */
    public function attempt(string $username, string $password): ?array
    {
        $user = $this->userModel->findByUsername($username);

        if (!$user) {
            return null;
        }

        if (!$user['is_active']) {
            return null;
        }

        if (!$this->userModel->verifyPassword($password, $user['password_hash'])) {
            return null;
        }

        // Update last login
        $this->userModel->updateLastLogin($user['id']);

        // Create session
        $sessionId = $this->createSession($user['id']);
        $user['session_id'] = $sessionId;

        return $user;
    }

    /**
     * Create a new session
     */
    public function createSession(int $userId): string
    {
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $this->config['session']['lifetime']);

        $stmt = $this->db->prepare("
            INSERT INTO admin_sessions (session_id, user_id, ip_address, user_agent, expires_at)
            VALUES (:session_id, :user_id, :ip_address, :user_agent, :expires_at)
        ");

        $stmt->execute([
            'session_id' => $sessionId,
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'expires_at' => $expiresAt,
        ]);

        return $sessionId;
    }

    /**
     * Validate a session
     */
    public function validateSession(string $sessionId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT s.*, u.username, u.email, u.full_name, u.role_id, u.is_active,
                   r.name as role_name, r.permissions
            FROM admin_sessions s
            JOIN admin_users u ON s.user_id = u.id
            LEFT JOIN admin_roles r ON u.role_id = r.id
            WHERE s.session_id = :session_id
              AND s.expires_at > CURRENT_TIMESTAMP
              AND u.is_active = 1
        ");

        $stmt->execute(['session_id' => $sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            $session['permissions'] = json_decode($session['permissions'], true);
            
            // Update last activity
            $this->updateSessionActivity($sessionId);
        }

        return $session ?: null;
    }

    /**
     * Update session last activity
     */
    private function updateSessionActivity(string $sessionId): void
    {
        $stmt = $this->db->prepare("
            UPDATE admin_sessions
            SET last_activity_at = CURRENT_TIMESTAMP
            WHERE session_id = :session_id
        ");
        $stmt->execute(['session_id' => $sessionId]);
    }

    /**
     * Destroy a session
     */
    public function destroySession(string $sessionId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM admin_sessions
            WHERE session_id = :session_id
        ");
        $stmt->execute(['session_id' => $sessionId]);
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions(): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM admin_sessions
            WHERE expires_at < CURRENT_TIMESTAMP
        ");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Get user from session
     */
    public function getUserFromSession(): ?array
    {
        if (!isset($_SESSION['admin_session_id'])) {
            return null;
        }

        return $this->validateSession($_SESSION['admin_session_id']);
    }

    /**
     * Check if user is authenticated
     */
    public function check(): bool
    {
        return $this->getUserFromSession() !== null;
    }

    /**
     * Get current authenticated user
     */
    public function user(): ?array
    {
        return $this->getUserFromSession();
    }

    /**
     * Log activity
     */
    public function logActivity(int $userId, string $action, string $resource, ?string $details = null): void
    {
        if (!$this->config['activity_log']['enabled']) {
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO admin_activity_log (user_id, action, resource, details, ip_address, user_agent)
            VALUES (:user_id, :action, :resource, :details, :ip_address, :user_agent)
        ");

        $stmt->execute([
            'user_id' => $userId,
            'action' => $action,
            'resource' => $resource,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ]);
    }

    /**
     * Generate CSRF token
     */
    public function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes($this->config['csrf']['token_length']));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        return $token;
    }

    /**
     * Validate CSRF token
     */
    public function validateCsrfToken(string $token): bool
    {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
            return false;
        }

        // Check if token has expired
        if (time() - $_SESSION['csrf_token_time'] > $this->config['csrf']['token_lifetime']) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

