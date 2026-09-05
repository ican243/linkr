<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddKakaoIdToUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'kakao_id' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'naver_id'],
        ]);
        $this->forge->addUniqueKey('kakao_id');
        $this->forge->processIndexes('users');
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'kakao_id');
    }
}
