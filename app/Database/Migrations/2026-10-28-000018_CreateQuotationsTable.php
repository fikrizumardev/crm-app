<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateQuotationsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'number'         => ['type' => 'VARCHAR', 'constraint' => 30],
            'deal_id'        => ['type' => 'BIGINT', 'unsigned' => true],
            'issue_date'     => ['type' => 'DATE'],
            'valid_until'    => ['type' => 'DATE'],
            'subtotal'       => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00'],
            'discount_total' => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00'],
            'tax_rate'       => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '0.00'],
            'tax_amount'     => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00'],
            'grand_total'    => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00'],
            'status'         => ['type' => 'ENUM', 'constraint' => ['draft', 'sent', 'accepted', 'rejected'], 'default' => 'draft'],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('number');
        $this->forge->addKey('status');
        $this->forge->addForeignKey('deal_id', 'deals', 'id', 'CASCADE', 'RESTRICT', 'quotations_deal_id_foreign');
        $this->forge->createTable('quotations', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('quotations', true);
    }
}
