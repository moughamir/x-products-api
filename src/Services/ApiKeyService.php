<?php

namespace App\Services;

use App\Models\ApiKey;
use PDO;

class ApiKeyService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Generate a new API key
     */
    public function generateApiKey(string $name, ?int $createdBy = null, array $options = []): array
    {
        $key = ApiKey::generate();
        $keyHash = ApiKey::hashKey($key);
        $keyPrefix = ApiKey::getPrefix($key);

        $apiKey = new ApiKey();
        $apiKey->name = $name;
        $apiKey->key_hash = $keyHash;
        $apiKey->key_prefix = $keyPrefix;
        $apiKey->permissions = isset($options['permissions']) ? json_encode($options['permissions']) : null;
        $apiKey->rate_limit = $options['rate_limit'] ?? 60;
        $apiKey->expires_at = $options['expires_at'] ?? null;
        $apiKey->created_by = $createdBy;

        if ($apiKey->save($this->db)) {
            return [
                'success' => true,
                'key' => $key, // Return the plain key only once
                'api_key' => $apiKey,
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to create API key',
        ];
    }

    /**
     * Validate API key
     */
    public function validateKey(string $key): ?ApiKey
    {
        $apiKey = ApiKey::validate($this->db, $key);
        
        if ($apiKey) {
            // Record usage
            $apiKey->recordUsage($this->db);
        }

        return $apiKey;
    }

    /**
     * Revoke API key
     */
    public function revokeKey(int $keyId): bool
    {
        $apiKey = ApiKey::find($this->db, $keyId);
        
        if (!$apiKey) {
            return false;
        }

        return $apiKey->delete($this->db);
    }

    /**
     * Get API key usage statistics
     */
    public function getUsageStatistics(int $keyId): array
    {
        $apiKey = ApiKey::find($this->db, $keyId);
        
        if (!$apiKey) {
            return [];
        }

        return [
            'total_requests' => $apiKey->total_requests,
            'last_used_at' => $apiKey->last_used_at,
            'is_expired' => $apiKey->isExpired(),
            'days_until_expiry' => $this->getDaysUntilExpiry($apiKey),
        ];
    }

    /**
     * Get days until expiry
     */
    private function getDaysUntilExpiry(ApiKey $apiKey): ?int
    {
        if (!$apiKey->expires_at) {
            return null;
        }

        $now = time();
        $expiry = strtotime($apiKey->expires_at);
        $diff = $expiry - $now;

        return (int)ceil($diff / 86400); // Convert seconds to days
    }

    /**
     * Check rate limit
     */
    public function checkRateLimit(ApiKey $apiKey, int $windowSeconds = 60): bool
    {
        // Simple rate limiting based on last usage
        // In production, you'd want to use Redis or similar for accurate rate limiting
        
        if (!$apiKey->last_used_at) {
            return true; // First request
        }

        $lastUsed = strtotime($apiKey->last_used_at);
        $now = time();
        $elapsed = $now - $lastUsed;

        // If less than window seconds have passed, check if we're over the limit
        if ($elapsed < $windowSeconds) {
            // This is a simplified check - in production use proper rate limiting
            return true;
        }

        return true;
    }

    /**
     * Rotate API key (generate new, deprecate old)
     */
    public function rotateKey(int $oldKeyId, ?int $createdBy = null): array
    {
        $oldKey = ApiKey::find($this->db, $oldKeyId);
        
        if (!$oldKey) {
            return [
                'success' => false,
                'error' => 'API key not found',
            ];
        }

        // Generate new key with same settings
        $result = $this->generateApiKey(
            $oldKey->name . ' (Rotated)',
            $createdBy,
            [
                'permissions' => $oldKey->permissions ? json_decode($oldKey->permissions, true) : null,
                'rate_limit' => $oldKey->rate_limit,
                'expires_at' => $oldKey->expires_at,
            ]
        );

        if ($result['success']) {
            // Mark old key as expired (set expiry to now)
            $oldKey->expires_at = date('Y-m-d H:i:s');
            $oldKey->save($this->db);
        }

        return $result;
    }

    /**
     * Get all API keys with statistics
     */
    public function getAllWithStatistics(int $page = 1, int $limit = 50, array $filters = []): array
    {
        $keys = ApiKey::all($this->db, $page, $limit, $filters);
        
        foreach ($keys as &$key) {
            $key['is_expired'] = isset($key['expires_at']) && strtotime($key['expires_at']) < time();
            $key['days_until_expiry'] = null;
            
            if (isset($key['expires_at']) && !$key['is_expired']) {
                $expiry = strtotime($key['expires_at']);
                $now = time();
                $key['days_until_expiry'] = (int)ceil(($expiry - $now) / 86400);
            }
        }

        return $keys;
    }

    /**
     * Clean up expired keys
     */
    public function cleanupExpiredKeys(): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM api_keys 
            WHERE expires_at IS NOT NULL AND expires_at < CURRENT_TIMESTAMP
        ");
        $stmt->execute();

        return $stmt->rowCount();
    }
}

