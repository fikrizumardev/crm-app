<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table         = 'settings';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['key', 'value'];

    /**
     * Cache sederhana per request.
     *
     * @var array<string, string|null>|null
     */
    private static ?array $cache = null;

    /**
     * @return array<string, string|null>
     */
    public function allValues(): array
    {
        if (self::$cache === null) {
            self::$cache = array_column($this->builder()->select('key, value')->get()->getResultArray(), 'value', 'key');
        }

        return self::$cache;
    }

    public function getValue(string $key, ?string $default = null): ?string
    {
        return $this->allValues()[$key] ?? $default;
    }

    public function setValue(string $key, ?string $value): void
    {
        $existing = $this->where('key', $key)->first();

        if ($existing === null) {
            $this->insert(['key' => $key, 'value' => $value]);
        } else {
            $this->update($existing['id'], ['value' => $value]);
        }

        self::$cache = null;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
