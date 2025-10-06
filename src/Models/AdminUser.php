<?php

namespace App\Models;

use PDO;

class AdminUser
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Find user by username
     */
    public function findByUsername(string $username): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name, r.permissions
            FROM admin_users u
            LEFT JOIN admin_roles r ON u.role_id = r.id
            WHERE u.username = :username
        ");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $user['permissions'] = json_decode($user['permissions'], true);
        }
        
        return $user ?: null;
    }

    /**
     * Find user by ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name, r.permissions
            FROM admin_users u
            LEFT JOIN admin_roles r ON u.role_id = r.id
            WHERE u.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $user['permissions'] = json_decode($user['permissions'], true);
        }
        
        return $user ?: null;
    }

    /**
     * Find user by email
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("
            SELECT u.*, r.name as role_name, r.permissions
            FROM admin_users u
            LEFT JOIN admin_roles r ON u.role_id = r.id
            WHERE u.email = :email
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $user['permissions'] = json_decode($user['permissions'], true);
        }
        
        return $user ?: null;
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(int $userId): void
    {
        $stmt = $this->db->prepare("
            UPDATE admin_users
            SET last_login_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $stmt->execute(['id' => $userId]);
    }

    /**
     * Check if user has permission
     */
    public function hasPermission(array $user, string $resource, string $action): bool
    {
        if (!isset($user['permissions'][$resource])) {
            return false;
        }

        return in_array($action, $user['permissions'][$resource]);
    }

    /**
     * Get all users
     */
    public function getAll(int $page = 1, int $limit = 50): array
    {
        $offset = ($page - 1) * $limit;
        
        $stmt = $this->db->prepare("
            SELECT u.id, u.username, u.email, u.full_name, u.is_active, 
                   u.last_login_at, u.created_at, r.name as role_name
            FROM admin_users u
            LEFT JOIN admin_roles r ON u.role_id = r.id
            ORDER BY u.created_at DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total user count
     */
    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM admin_users");
        return (int) $stmt->fetchColumn();
    }

    /**
     * Create new user
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO admin_users (username, email, password_hash, full_name, role_id, is_active)
            VALUES (:username, :email, :password_hash, :full_name, :role_id, :is_active)
        ");
        
        $stmt->execute([
            'username' => $data['username'],
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            'full_name' => $data['full_name'] ?? '',
            'role_id' => $data['role_id'],
            'is_active' => $data['is_active'] ?? 1,
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update user
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = ['id' => $id];
        
        if (isset($data['username'])) {
            $fields[] = 'username = :username';
            $params['username'] = $data['username'];
        }
        
        if (isset($data['email'])) {
            $fields[] = 'email = :email';
            $params['email'] = $data['email'];
        }
        
        if (isset($data['full_name'])) {
            $fields[] = 'full_name = :full_name';
            $params['full_name'] = $data['full_name'];
        }
        
        if (isset($data['role_id'])) {
            $fields[] = 'role_id = :role_id';
            $params['role_id'] = $data['role_id'];
        }
        
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = :is_active';
            $params['is_active'] = $data['is_active'];
        }
        
        if (isset($data['password'])) {
            $fields[] = 'password_hash = :password_hash';
            $params['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        
        $sql = "UPDATE admin_users SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute($params);
    }

    /**
     * Delete user
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM admin_users WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}

