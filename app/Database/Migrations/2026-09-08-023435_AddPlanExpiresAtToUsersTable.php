<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPlanExpiresAtToUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            // 지금 요금제의 이용 만료일. 실제 결제 승인 시(+1개월)와 관리자가 강제로
            // 부여할 때(+N개월) 둘 다 여기에 기록됨. 무료 요금제는 항상 null.
            'plan_expires_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'plan'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['plan_expires_at']);
    }
}
