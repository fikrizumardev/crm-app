<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePipelineStagesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 50],
            'probability' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'sort_order'  => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'is_won'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_lost'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('name');
        $this->forge->addKey('sort_order');
        $this->forge->createTable('pipeline_stages', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('pipeline_stages', true);
    }
}
