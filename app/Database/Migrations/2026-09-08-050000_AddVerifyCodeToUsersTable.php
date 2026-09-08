<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVerifyCodeToUsersTable extends Migration
{
    public function up()
    {
        // TXT 레코드 검증용 코드. 도메인을 새로 등록/변경할 때마다 새로 생성해서,
        // 실제로 그 도메인의 DNS를 관리할 수 있는 사람만 인증을 통과할 수 있게 한다.
        $this->forge->addColumn('users', [
            'custom_domain_verify_code' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'custom_domain_verified'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['custom_domain_verify_code']);
    }
}
