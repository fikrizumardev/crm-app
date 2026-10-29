<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTasksTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'           => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'title'        => ['type' => 'VARCHAR', 'constraint' => 200],
            'description'  => ['type' => 'TEXT', 'null' => true],
            'due_at'       => ['type' => 'DATETIME'],
            'priority'     => ['type' => 'ENUM', 'constraint' => ['low', 'medium', 'high'], 'default' => 'medium'],
            'status'       => ['type' => 'ENUM', 'constraint' => ['open', 'done'], 'default' => 'open'],
            'completed_at' => ['type' => 'DATETIME', 'null' => true],
            'related_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'related_id'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'assigned_to'  => ['type' => 'BIGINT', 'unsigned' => true],
            'created_by'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['assigned_to', 'status', 'due_at']);
        $this->forge->addKey(['related_type', 'related_id']);
        $this->forge->addForeignKey('assigned_to', 'users', 'id', 'CASCADE', 'RESTRICT', 'tasks_assigned_to_foreign');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'SET NULL', 'tasks_created_by_foreign');
        $this->forge->createTable('tasks', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('tasks', true);
    }
}
