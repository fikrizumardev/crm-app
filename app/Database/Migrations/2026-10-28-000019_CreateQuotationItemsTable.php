<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQuotationItemsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'quotation_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'product_id'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'description'  => ['type' => 'VARCHAR', 'constraint' => 255],
            'qty'          => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => '1.00'],
            'unit_price'   => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00'],
            'discount_pct' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '0.00'],
            'line_total'   => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00'],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('quotation_id', 'quotations', 'id', 'CASCADE', 'CASCADE', 'quotation_items_quotation_id_foreign');
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'SET NULL', 'quotation_items_product_id_foreign');
        $this->forge->createTable('quotation_items', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('quotation_items', true);
    }
}
