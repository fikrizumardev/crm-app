<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDealsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                  => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'title'               => ['type' => 'VARCHAR', 'constraint' => 200],
            'company_id'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'contact_id'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'stage_id'            => ['type' => 'BIGINT', 'unsigned' => true],
            'amount'              => ['type' => 'DECIMAL', 'constraint' => '15,2', 'default' => '0.00'],
            'probability'         => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'expected_close_date' => ['type' => 'DATE', 'null' => true],
            'closed_at'           => ['type' => 'DATETIME', 'null' => true],
            'lost_reason_id'      => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'owner_id'            => ['type' => 'BIGINT', 'unsigned' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'          => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['owner_id', 'stage_id']);
        $this->forge->addKey('expected_close_date');
        $this->forge->addKey('closed_at');
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'SET NULL', 'deals_company_id_foreign');
        $this->forge->addForeignKey('contact_id', 'contacts', 'id', 'CASCADE', 'SET NULL', 'deals_contact_id_foreign');
        $this->forge->addForeignKey('stage_id', 'pipeline_stages', 'id', 'CASCADE', 'RESTRICT', 'deals_stage_id_foreign');
        $this->forge->addForeignKey('lost_reason_id', 'lost_reasons', 'id', 'CASCADE', 'RESTRICT', 'deals_lost_reason_id_foreign');
        $this->forge->addForeignKey('owner_id', 'users', 'id', 'CASCADE', 'RESTRICT', 'deals_owner_id_foreign');
        $this->forge->createTable('deals', true, ['ENGINE' => 'InnoDB']);

        // Lengkapi relasi hasil konversi lead → deal.
        $this->forge->addForeignKey('converted_deal_id', 'deals', 'id', 'CASCADE', 'SET NULL', 'leads_converted_deal_id_foreign');
        $this->forge->processIndexes('leads');
    }

    public function down(): void
    {
        $this->forge->dropForeignKey('leads', 'leads_converted_deal_id_foreign');
        $this->forge->dropTable('deals', true);
    }
}
