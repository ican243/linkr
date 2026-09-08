<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminLogModel extends Model
{
    protected $table            = 'admin_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['admin_id', 'action', 'target_type', 'target_id', 'detail'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    // 관리자 화면 여기저기서 "이런 액션을 했다"만 남기면 되게, 매개변수 4개짜리로 단순화해둠.
    public function record(int $adminId, string $action, string $targetType, int $targetId, string $detail): void
    {
        $this->insert([
            'admin_id'    => $adminId,
            'action'      => $action,
            'target_type' => $targetType,
            'target_id'   => $targetId,
            'detail'      => $detail,
        ]);
    }
}
