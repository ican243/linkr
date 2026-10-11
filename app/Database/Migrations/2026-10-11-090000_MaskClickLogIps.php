<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// 이전에 전체 IP로 저장된 클릭 기록도 마지막 자리를 가린 형태로 바꿈 (앞으로 저장되는 값은 Link::doRedirect에서 가려짐).
// 원래 IP로 되돌릴 수 없으므로 down()은 아무것도 하지 않음.
class MaskClickLogIps extends Migration
{
    public function up()
    {
        helper('link');

        $rows = $this->db->table('click_logs')
            ->select('id, ip_address')
            ->where('ip_address IS NOT NULL')
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $masked = mask_ip($row['ip_address']);

            if ($masked !== $row['ip_address']) {
                $this->db->table('click_logs')->where('id', $row['id'])->update(['ip_address' => $masked]);
            }
        }
    }

    public function down()
    {
    }
}
