<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                   => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'username'             => ['type' => 'VARCHAR', 'constraint' => 30],
            'password_hash'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'name'                 => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'                => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'phone'                => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'role'                 => ['type' => 'ENUM', 'constraint' => ['admin', 'manager', 'sales'], 'default' => 'sales'],
            'manager_id'           => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'is_active'            => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'must_change_password' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'last_login_at'        => ['type' => 'DATETIME', 'null' => true],
            'created_at'           => ['type' => 'DATETIME', 'null' => true],
            'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('username');
        $this->forge->addKey(['role', 'is_active']);
        $this->forge->addForeignKey('manager_id', 'users', 'id', 'CASCADE', 'SET NULL', 'users_manager_id_foreign');
        $this->forge->createTable('users', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('users', true);
    }
}
