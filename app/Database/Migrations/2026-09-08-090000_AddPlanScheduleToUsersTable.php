<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPlanScheduleToUsersTable extends Migration
{
    public function up()
    {
        // 다운그레이드는 결제/환불 없이 "예약"만 해두고, 현재 이용기간이 끝나는 시점에
        // 실제로 적용됨. next_plan_at은 예약 당시의 plan_expires_at을 그대로 복사해둔 값.
        $this->forge->addColumn('users', [
            'next_plan'    => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'plan_expires_at'],
            'next_plan_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'next_plan'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['next_plan', 'next_plan_at']);
    }
}
