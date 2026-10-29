<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateActivitiesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'type'         => ['type' => 'ENUM', 'constraint' => ['call', 'meeting', 'email', 'whatsapp', 'visit', 'note']],
            'subject'      => ['type' => 'VARCHAR', 'constraint' => 200],
            'description'  => ['type' => 'TEXT', 'null' => true],
            'related_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'related_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'activity_at'  => ['type' => 'DATETIME'],
            'user_id'      => ['type' => 'BIGINT', 'unsigned' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['related_type', 'related_id']);
        $this->forge->addKey(['user_id', 'activity_at']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'RESTRICT', 'activities_user_id_foreign');
        $this->forge->createTable('activities', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('activities', true);
    }
}
