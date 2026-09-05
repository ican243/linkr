<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLinksTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'original_url' => ['type' => 'VARCHAR', 'constraint' => 2048],
            'short_code'   => ['type' => 'VARCHAR', 'constraint' => 20],
            'click_count'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('short_code');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('links');
    }

    public function down()
    {
        $this->forge->dropTable('links');
    }
}
