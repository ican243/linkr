<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPasswordToLinksTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('links', [
            'password' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'short_code'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('links', 'password');
    }
}
