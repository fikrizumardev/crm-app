<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProductsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'sku'        => ['type' => 'VARCHAR', 'constraint' => 50],
            'name'       => ['type' => 'VARCHAR', 'constraint' => 200],
            'unit'       => ['type' => 'VARCHAR', 'constraint' => 30],
            'price'      => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00'],
            'is_active'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('sku');
        $this->forge->addKey('is_active');
        $this->forge->createTable('products', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('products', true);
    }
}
