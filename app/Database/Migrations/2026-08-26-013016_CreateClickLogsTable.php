<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClickLogsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'link_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'clicked_at' => ['type' => 'DATETIME'],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('link_id', 'links', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('click_logs');
    }

    public function down()
    {
        $this->forge->dropTable('click_logs');
    }
}
