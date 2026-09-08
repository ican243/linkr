<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdminLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'admin_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            // 'refund' | 'status_change' | 'memo_update' | 'plan_change'(C단계에서 추가 예정)
            'action'      => ['type' => 'VARCHAR', 'constraint' => 30],
            // 뭘 대상으로 한 액션인지. 지금은 'payment'만 쓰지만 C단계에서 'user'도 씀.
            'target_type' => ['type' => 'VARCHAR', 'constraint' => 30],
            'target_id'   => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'detail'      => ['type' => 'TEXT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('admin_id', 'admins', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('admin_logs');
    }

    public function down()
    {
        $this->forge->dropTable('admin_logs');
    }
}
