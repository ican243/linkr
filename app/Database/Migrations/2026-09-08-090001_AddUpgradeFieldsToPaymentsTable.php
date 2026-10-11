<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUpgradeFieldsToPaymentsTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('payments', [
            // 신규 / 업그레이드(차액) / 갱신 — Payment::checkout()에서 결정해서 저장.
            'type'            => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'new', 'after' => 'plan'],
            // 업그레이드 건일 때 "프로 잔여 15일(16,500원) 차감" 같은 계산 내역을 사람이 읽을 수 있게 기록.
            // admin_memo(관리자가 직접 쓰는 메모)와는 용도가 달라서 컬럼을 분리함.
            'change_note'     => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'type'],
            // 체크아웃 시점에 서버가 계산한 "이 주문에서 받아야 할 금액". 토스 콜백(success())이
            // 돌려주는 실제 결제금액과 이 값을 비교해서, 다르면 위변조로 간주하고 실패 처리함.
            'expected_amount' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'amount'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('payments', ['type', 'change_note', 'expected_amount']);
    }
}
