<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddReceiptUrlToPaymentsTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('payments', [
            // 토스페이먼츠 결제승인 응답의 receipt.url을 그대로 저장해서,
            // 결제 내역 화면에서 "영수증 보기" 버튼으로 바로 연결하는 데 씀.
            'receipt_url' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'method'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('payments', ['receipt_url']);
    }
}
