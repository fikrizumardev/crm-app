<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLeadsTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'                   => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'name'                 => ['type' => 'VARCHAR', 'constraint' => 150],
            'company_name'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'job_title'            => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'phone'                => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'email'                => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'source_id'            => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'status'               => ['type' => 'ENUM', 'constraint' => ['new', 'contacted', 'qualified', 'unqualified', 'converted'], 'default' => 'new'],
            'owner_id'             => ['type' => 'BIGINT', 'unsigned' => true],
            'notes'                => ['type' => 'TEXT', 'null' => true],
            'converted_contact_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'converted_company_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            // FK ke deals ditambahkan di migration deals karena tabelnya dibuat setelah ini.
            'converted_deal_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'converted_at'      => ['type' => 'DATETIME', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['owner_id', 'status']);
        $this->forge->addKey('phone');
        $this->forge->addKey('email');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('source_id', 'lead_sources', 'id', 'CASCADE', 'SET NULL', 'leads_source_id_foreign');
        $this->forge->addForeignKey('owner_id', 'users', 'id', 'CASCADE', 'RESTRICT', 'leads_owner_id_foreign');
        $this->forge->addForeignKey('converted_contact_id', 'contacts', 'id', 'CASCADE', 'SET NULL', 'leads_converted_contact_id_foreign');
        $this->forge->addForeignKey('converted_company_id', 'companies', 'id', 'CASCADE', 'SET NULL', 'leads_converted_company_id_foreign');
        $this->forge->createTable('leads', true, ['ENGINE' => 'InnoDB']);
    }

    public function down(): void
    {
        $this->forge->dropTable('leads', true);
    }
}
