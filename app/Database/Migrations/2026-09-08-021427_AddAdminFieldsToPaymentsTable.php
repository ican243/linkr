<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAdminFieldsToPaymentsTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('payments', [
            // 이 결제 건을 마지막으로 건드린 관리자. 환불/상태변경 처리자를 남기는 용도.
            'admin_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'receipt_url'],
            'admin_memo'    => ['type' => 'TEXT', 'null' => true, 'after' => 'admin_id'],
            'refunded_at'   => ['type' => 'DATETIME', 'null' => true, 'after' => 'admin_memo'],
            'cancel_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'refunded_at'],
            // 토스 API(승인/취소) 응답 원본. 관리자 상세 모달에서 디버깅용으로 그대로 보여줌.
            // 환불하면 취소 응답으로 덮어써서, 항상 "가장 최근에 실제로 일어난 일"만 담음.
            'toss_response' => ['type' => 'TEXT', 'null' => true, 'after' => 'cancel_reason'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('payments', ['admin_id', 'admin_memo', 'refunded_at', 'cancel_reason', 'toss_response']);
    }
}
