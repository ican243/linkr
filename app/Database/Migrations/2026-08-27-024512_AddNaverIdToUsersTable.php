<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNaverIdToUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'naver_id' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'google_id'],
        ]);
        $this->forge->addUniqueKey('naver_id');
        $this->forge->processIndexes('users');
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'naver_id');
    }
}
