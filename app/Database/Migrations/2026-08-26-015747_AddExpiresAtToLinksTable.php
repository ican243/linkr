<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddExpiresAtToLinksTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('links', [
            'expires_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'password'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('links', 'expires_at');
    }
}
