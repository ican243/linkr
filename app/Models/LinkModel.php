<?php

namespace App\Models;

use CodeIgniter\Model;

class LinkModel extends Model
{
    protected $table            = 'links';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['user_id', 'original_url', 'title', 'short_code', 'password', 'expires_at', 'click_count', 'fallback_url', 'max_clicks', 'expiry_notified_at'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // 짧은 코드가 이미 쓰이고 있는지 매번 확인해서, 우연히 같은 코드가 두 번 생성되는 걸 막는다.
    // 웹 화면(Link 컨트롤러)과 API(Api\Links 컨트롤러) 양쪽에서 똑같이 쓰기 때문에 모델에 둠.
    public function generateUniqueShortCode(int $length = 6): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $max   = strlen($chars) - 1;

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $chars[random_int(0, $max)];
            }
        } while ($this->where('short_code', $code)->first());

        return $code;
    }
}
