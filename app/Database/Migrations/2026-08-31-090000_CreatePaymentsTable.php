<?php

 namespace App\Database\Migrations;
  
  use CodeIgniter\Database\Migration;
  
  class CreatePaymentsTable extends Migration
  {
      public function up()
      {
          $this->forge->addField([
              'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
              'user_id'      => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
              'order_id'     => ['type' => 'VARCHAR', 'constraint' => 64],
              'amount'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
              'plan'         => ['type' => 'VARCHAR', 'constraint' => 20],
              'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'],
              'payment_key'  => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
              'method'       => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
              'created_at'   => ['type' => 'DATETIME', 'null' => true],
              'approved_at'  => ['type' => 'DATETIME', 'null' => true],
          ]); 
          $this->forge->addKey('id', true);
          $this->forge->addUniqueKey('order_id');
          $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
          $this->forge->createTable('payments'); 
      }   
      
      public function down()
      {
          $this->forge->dropTable('payments');
      }   
  }   