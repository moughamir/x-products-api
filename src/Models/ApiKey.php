<?php

namespace App\Models;

use PDO;

class ApiKey
{
    public ?int $id = null;
    public string $name;
    public string $key_hash;
    public string $key_prefix;
    public ?string $permissions = null;
    public int $rate_limit = 60;
    public ?string $expires_at = null;
    public ?string $last_used_at = null;
    public int $total_requests = 0;
    public ?int $created_by = null;
    public ?string $created_at = null;

    /**
     * Find API key by ID
     */
    public static function find(PDO $db, int $id): ?self
    {
        $stmt = $db->prepare("SELECT * FROM api_keys WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::class);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find API key by key hash
     */
    public static function findByKeyHash(PDO $db, string $keyHash): ?self
    {
        $stmt = $db->prepare("SELECT * FROM api_keys WHERE key_hash = :key_hash");
        $stmt->execute(['key_hash' => $keyHash]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::class);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Get all API keys with pagination
     */
    public static function all(PDO $db, int $page = 1, int $limit = 50, array $filters = []): array
    {
        $offset = ($page - 1) * $limit;
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "name LIKE :search";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['created_by'])) {
            $where[] = "created_by = :created_by";
            $params['created_by'] = $filters['created_by'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $sql = "
            SELECT ak.*, au.username as created_by_username
            FROM api_keys ak
            LEFT JOIN admin_users au ON ak.created_by = au.id
            {$whereClause}
            ORDER BY ak.created_at DESC
            LIMIT :limit OFFSET :offset
        ";
        
        $stmt = $db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Count total API keys
     */
    public static function count(PDO $db, array $filters = []): int
    {
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = "name LIKE :search";
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (isset($filters['created_by'])) {
            $where[] = "created_by = :created_by";
            $params['created_by'] = $filters['created_by'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM api_keys {$whereClause}");
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    /**
     * Generate a new API key
     */
    public static function generate(): string
    {
        return bin2hex(random_bytes(32)); // 64 character hex string
    }

    /**
     * Hash an API key for storage
     */
    public static function hashKey(string $key): string
    {
        return hash('sha256', $key);
    }

    /**
     * Get key prefix (first 8 characters for display)
     */
    public static function getPrefix(string $key): string
    {
        return substr($key, 0, 8);
    }

    /**
     * Save API key (insert or update)
     */
    public function save(PDO $db): bool
    {
        if ($this->id) {
            return $this->update($db);
        } else {
            return $this->insert($db);
        }
    }

    /**
     * Insert new API key
     */
    private function insert(PDO $db): bool
    {
        $stmt = $db->prepare("
            INSERT INTO api_keys (name, key_hash, key_prefix, permissions, rate_limit, expires_at, created_by, created_at)
            VALUES (:name, :key_hash, :key_prefix, :permissions, :rate_limit, :expires_at, :created_by, CURRENT_TIMESTAMP)
        ");
        
        $result = $stmt->execute([
            'name' => $this->name,
            'key_hash' => $this->key_hash,
            'key_prefix' => $this->key_prefix,
            'permissions' => $this->permissions,
            'rate_limit' => $this->rate_limit,
            'expires_at' => $this->expires_at,
            'created_by' => $this->created_by,
        ]);

        if ($result) {
            $this->id = (int)$db->lastInsertId();
        }

        return $result;
    }

    /**
     * Update existing API key
     */
    private function update(PDO $db): bool
    {
        $stmt = $db->prepare("
            UPDATE api_keys 
            SET name = :name, permissions = :permissions, rate_limit = :rate_limit, expires_at = :expires_at
            WHERE id = :id
        ");
        
        return $stmt->execute([
            'id' => $this->id,
            'name' => $this->name,
            'permissions' => $this->permissions,
            'rate_limit' => $this->rate_limit,
            'expires_at' => $this->expires_at,
        ]);
    }

    /**
     * Delete API key (revoke)
     */
    public function delete(PDO $db): bool
    {
        $stmt = $db->prepare("DELETE FROM api_keys WHERE id = :id");
        return $stmt->execute(['id' => $this->id]);
    }

    /**
     * Record API key usage
     */
    public function recordUsage(PDO $db): bool
    {
        $stmt = $db->prepare("
            UPDATE api_keys 
            SET last_used_at = CURRENT_TIMESTAMP, total_requests = total_requests + 1
            WHERE id = :id
        ");
        
        return $stmt->execute(['id' => $this->id]);
    }

    /**
     * Check if API key is expired
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        
        return strtotime($this->expires_at) < time();
    }

    /**
     * Check if API key is valid
     */
    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    /**
     * Validate API key against hash
     */
    public static function validate(PDO $db, string $key): ?self
    {
        $keyHash = self::hashKey($key);
        $apiKey = self::findByKeyHash($db, $keyHash);
        
        if (!$apiKey || !$apiKey->isValid()) {
            return null;
        }
        
        return $apiKey;
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key_prefix' => $this->key_prefix,
            'permissions' => $this->permissions ? json_decode($this->permissions, true) : null,
            'rate_limit' => $this->rate_limit,
            'expires_at' => $this->expires_at,
            'last_used_at' => $this->last_used_at,
            'total_requests' => $this->total_requests,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'is_expired' => $this->isExpired(),
        ];
    }
}

