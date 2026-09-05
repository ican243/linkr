<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['email', 'password', 'name', 'plan', 'google_id', 'naver_id', 'kakao_id', 'custom_domain', 'custom_domain_verified'];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // 검증(validation) 규칙은 여기가 아니라 Auth 컨트롤러에서 처리한다.
    // 이 모델의 insert()는 이미 해시된 비밀번호를 받기 때문에, 여기서 min_length 같은
    // 규칙을 걸면 해시 문자열(항상 60자 이상) 기준으로 검사돼 의미가 없어진다.
}
