<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateApiKeysTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'label'        => ['type' => 'VARCHAR', 'constraint' => 100],
            // 원본 키는 저장하지 않고 sha256 해시(64자 hex)만 저장한다. 비밀번호와 달리
            // 매 API 요청마다 "정확히 일치하는지"만 빠르게 비교하면 되므로 bcrypt 대신 sha256을 씀.
            'key_hash'     => ['type' => 'VARCHAR', 'constraint' => 64],
            // 목록 화면에 "king_****...ab12" 처럼 마지막 4자리만 보여주기 위한 표시용 값.
            'key_last4'    => ['type' => 'VARCHAR', 'constraint' => 4],
            'last_used_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('key_hash');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('api_keys');
    }

    public function down()
    {
        $this->forge->dropTable('api_keys');
    }
}
