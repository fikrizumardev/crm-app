<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDealStageHistoryTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'deal_id'       => ['type' => 'BIGINT', 'unsigned' => true],
            'from_stage_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'to_stage_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'changed_by'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'changed_at'    => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['deal_id', 'changed_at']);
        $this->forge->addForeignKey('deal_id', 'deals', 'id', 'CASCADE', 'CASCADE', 'deal_stage_history_deal_id_foreign');
        $this->forge->addForeignKey('from_stage_id', 'pipeline_stages', 'id', 'CASCADE', 'RESTRICT', 'deal_stage_history_from_stage_id_foreign');
        $this->forge->addForeignKey('to_stage_id', 'pipeline_stages', 'id', 'CASCADE', 'RESTRICT', 'deal_stage_history_to_stage_id_foreign');
        $this->forge->addForeignKey('changed_by', 'users', 'id', 'CASCADE', 'SET NULL', 'deal_stage_history_changed_by_foreign');
        $this->forge->createTable('deal_stage_history', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('deal_stage_history', true);
    }
}
