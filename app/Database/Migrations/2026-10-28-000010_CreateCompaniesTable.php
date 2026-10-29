<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCompaniesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'             => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 150],
            'industry_id'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'npwp'           => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
            'phone'          => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'website'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'address'        => ['type' => 'TEXT', 'null' => true],
            'city'           => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'province'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'employee_count' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'owner_id'       => ['type' => 'BIGINT', 'unsigned' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('name');
        $this->forge->addKey('city');
        $this->forge->addKey(['owner_id', 'deleted_at']);
        $this->forge->addForeignKey('industry_id', 'industries', 'id', 'CASCADE', 'SET NULL', 'companies_industry_id_foreign');
        $this->forge->addForeignKey('owner_id', 'users', 'id', 'CASCADE', 'RESTRICT', 'companies_owner_id_foreign');
        $this->forge->createTable('companies', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('companies', true);
    }
}
