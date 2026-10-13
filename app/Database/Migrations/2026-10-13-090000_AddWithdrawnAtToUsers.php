<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWithdrawnAtToUsers extends Migration
{
    public function up()
    {
        // 회원 탈퇴 시각. 결제 기록이 있는 회원은 결제 기록(법정 5년 보관)이 회원 번호를 가리키고 있어서
        // 회원 줄 자체는 남기되, 개인정보(이메일·이름·소셜 ID 등)는 모두 지우고 이 칸에 탈퇴 시각을 적어둠.
        // (자세한 처리는 app/Libraries/AccountDeletion.php)
        $this->forge->addColumn('users', [
            'withdrawn_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'custom_domain_verify_code'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['withdrawn_at']);
    }
}
