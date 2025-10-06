<?php

namespace App\Models;

use PDO;

class Setting
{
    public string $key;
    public ?string $value = null;
    public string $type = 'string';
    public ?string $updated_at = null;

    /**
     * Get setting by key
     */
    public static function get(PDO $db, string $key, $default = null)
    {
        $stmt = $db->prepare("SELECT * FROM settings WHERE key = :key");
        $stmt->execute(['key' => $key]);
        $stmt->setFetchMode(PDO::FETCH_CLASS, self::class);
        $setting = $stmt->fetch();
        
        if (!$setting) {
            return $default;
        }
        
        return $setting->getCastedValue();
    }

    /**
     * Set setting value
     */
    public static function set(PDO $db, string $key, $value, string $type = 'string'): bool
    {
        $setting = new self();
        $setting->key = $key;
        $setting->value = self::castToString($value, $type);
        $setting->type = $type;
        
        return $setting->save($db);
    }

    /**
     * Get all settings
     */
    public static function all(PDO $db): array
    {
        $stmt = $db->query("SELECT * FROM settings ORDER BY key ASC");
        $settings = $stmt->fetchAll(PDO::FETCH_CLASS, self::class);
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = $setting->getCastedValue();
        }
        
        return $result;
    }

    /**
     * Get settings by prefix
     */
    public static function getByPrefix(PDO $db, string $prefix): array
    {
        $stmt = $db->prepare("SELECT * FROM settings WHERE key LIKE :prefix ORDER BY key ASC");
        $stmt->execute(['prefix' => $prefix . '%']);
        $settings = $stmt->fetchAll(PDO::FETCH_CLASS, self::class);
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = $setting->getCastedValue();
        }
        
        return $result;
    }

    /**
     * Save setting (insert or update)
     */
    public function save(PDO $db): bool
    {
        $stmt = $db->prepare("
            INSERT INTO settings (key, value, type, updated_at)
            VALUES (:key, :value, :type, CURRENT_TIMESTAMP)
            ON CONFLICT(key) DO UPDATE SET
                value = :value,
                type = :type,
                updated_at = CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute([
            'key' => $this->key,
            'value' => $this->value,
            'type' => $this->type,
        ]);
    }

    /**
     * Delete setting
     */
    public static function delete(PDO $db, string $key): bool
    {
        $stmt = $db->prepare("DELETE FROM settings WHERE key = :key");
        return $stmt->execute(['key' => $key]);
    }

    /**
     * Get value casted to appropriate type
     */
    public function getCastedValue()
    {
        if ($this->value === null) {
            return null;
        }
        
        switch ($this->type) {
            case 'int':
            case 'integer':
                return (int)$this->value;
            
            case 'float':
            case 'double':
                return (float)$this->value;
            
            case 'bool':
            case 'boolean':
                return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
            
            case 'json':
            case 'array':
                return json_decode($this->value, true);
            
            case 'string':
            default:
                return $this->value;
        }
    }

    /**
     * Cast value to string for storage
     */
    private static function castToString($value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }
        
        switch ($type) {
            case 'json':
            case 'array':
                return json_encode($value);
            
            case 'bool':
            case 'boolean':
                return $value ? '1' : '0';
            
            default:
                return (string)$value;
        }
    }

    /**
     * Bulk update settings
     */
    public static function bulkUpdate(PDO $db, array $settings): bool
    {
        $db->beginTransaction();
        
        try {
            foreach ($settings as $key => $value) {
                // Determine type
                $type = 'string';
                if (is_int($value)) {
                    $type = 'int';
                } elseif (is_float($value)) {
                    $type = 'float';
                } elseif (is_bool($value)) {
                    $type = 'bool';
                } elseif (is_array($value)) {
                    $type = 'json';
                }
                
                self::set($db, $key, $value, $type);
            }
            
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            return false;
        }
    }

    /**
     * Get default settings
     */
    public static function getDefaults(): array
    {
        return [
            // General
            'app_name' => 'Cosmos Admin',
            'app_description' => 'Product Management System',
            'timezone' => 'UTC',
            'date_format' => 'Y-m-d H:i:s',
            
            // Products
            'default_currency' => 'USD',
            'items_per_page' => 50,
            'max_image_size' => 10485760, // 10 MB
            'allowed_image_types' => 'jpg,jpeg,png,gif,webp',
            
            // Email
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'from_email' => '',
            'from_name' => 'Cosmos Admin',
        ];
    }

    /**
     * Initialize default settings
     */
    public static function initializeDefaults(PDO $db): bool
    {
        $defaults = self::getDefaults();
        return self::bulkUpdate($db, $defaults);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'value' => $this->getCastedValue(),
            'type' => $this->type,
            'updated_at' => $this->updated_at,
        ];
    }
}

