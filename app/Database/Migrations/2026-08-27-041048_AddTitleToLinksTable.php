<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTitleToLinksTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('links', [
            'title' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'original_url'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('links', 'title');
    }
}
