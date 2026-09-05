<?php

namespace App\Models;

use CodeIgniter\Model;

class ClickLogModel extends Model
{
    protected $table            = 'click_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['link_id', 'clicked_at', 'ip_address', 'user_agent'];
}
