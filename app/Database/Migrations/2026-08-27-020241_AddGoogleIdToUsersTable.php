<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGoogleIdToUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'google_id' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'name'],
        ]);
        $this->forge->addUniqueKey('google_id');
        $this->forge->processIndexes('users');
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'google_id');
    }
}
