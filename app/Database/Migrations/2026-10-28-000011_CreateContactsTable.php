<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateContactsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'company_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'last_name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'job_title'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'phone'      => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'whatsapp'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'birth_date' => ['type' => 'DATE', 'null' => true],
            'owner_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('phone');
        $this->forge->addKey('email');
        $this->forge->addKey(['first_name', 'last_name']);
        $this->forge->addKey(['owner_id', 'deleted_at']);
        $this->forge->addForeignKey('company_id', 'companies', 'id', 'CASCADE', 'SET NULL', 'contacts_company_id_foreign');
        $this->forge->addForeignKey('owner_id', 'users', 'id', 'CASCADE', 'RESTRICT', 'contacts_owner_id_foreign');
        $this->forge->createTable('contacts', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('contacts', true);
    }
}
