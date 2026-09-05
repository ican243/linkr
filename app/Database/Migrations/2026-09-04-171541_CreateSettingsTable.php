<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSettingsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'setting_key'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'setting_value' => ['type' => 'VARCHAR', 'constraint' => 255],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('setting_key');
        $this->forge->createTable('settings');

        // 지금까지 코드에 하드코딩되어 있던 값들을 그대로 초깃값으로 넣어둔다.
        // (admin/pricing.php/Payment.php/Link.php에 흩어져 있던 값들과 반드시 같아야 함)
        $this->db->table('settings')->insertBatch([
            ['setting_key' => 'free_plan_link_limit', 'setting_value' => '100', 'updated_at' => date('Y-m-d H:i:s')],
            ['setting_key' => 'pro_plan_price', 'setting_value' => '33000', 'updated_at' => date('Y-m-d H:i:s')],
            ['setting_key' => 'enterprise_plan_price', 'setting_value' => '79800', 'updated_at' => date('Y-m-d H:i:s')],
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('settings');
    }
}
