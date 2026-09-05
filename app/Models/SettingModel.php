<?php

namespace App\Models;

use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table            = 'settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['setting_key', 'setting_value'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = '';
    protected $updatedField  = 'updated_at';

    public function getValue(string $key, ?string $default = null): ?string
    {
        $row = $this->where('setting_key', $key)->first();

        return $row['setting_value'] ?? $default;
    }

    // 있으면 값만 갱신, 없으면 새로 만듦 (admin 설정 화면에서 저장할 때 씀).
    public function setValue(string $key, string $value): void
    {
        $existing = $this->where('setting_key', $key)->first();

        if ($existing) {
            $this->update($existing['id'], ['setting_value' => $value]);
        } else {
            $this->insert(['setting_key' => $key, 'setting_value' => $value]);
        }
    }
}
