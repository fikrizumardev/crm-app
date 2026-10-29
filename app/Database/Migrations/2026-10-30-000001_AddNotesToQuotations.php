<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Catatan/syarat penawaran (tampil di halaman cetak) dan pembuat penawaran.
 */
class AddNotesToQuotations extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('quotations', [
            'notes'      => ['type' => 'TEXT', 'null' => true, 'after' => 'status'],
            'created_by' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'notes'],
        ]);

        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'SET NULL', 'quotations_created_by_foreign');
        $this->forge->processIndexes('quotations');
    }

    public function down(): void
    {
        $this->forge->dropForeignKey('quotations', 'quotations_created_by_foreign');
        $this->forge->dropColumn('quotations', ['notes', 'created_by']);
    }
}
