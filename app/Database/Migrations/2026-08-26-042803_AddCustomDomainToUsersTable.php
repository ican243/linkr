<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCustomDomainToUsersTable extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'custom_domain'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'plan'],
            'custom_domain_verified' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'after' => 'custom_domain'],
        ]);
        $this->forge->addUniqueKey('custom_domain');
        $this->forge->processIndexes('users');
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['custom_domain', 'custom_domain_verified']);
    }
}
