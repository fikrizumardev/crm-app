<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Indeks tambahan hasil uji performa 10.000 baris (T5.08): urutan default daftar
 * memakai created_at, dan daftar selalu memfilter baris yang tidak dihapus.
 *
 * Memakai ALTER TABLE eksplisit karena Forge::processIndexes() bisa memakai daftar kolom
 * sisa operasi forge sebelumnya dan melewati indeks tanpa error. Hindari indeks yang kolom pertamanya
 * kolom foreign key: MySQL akan memakainya menggantikan indeks FK sehingga tidak bisa di-rollback.
 */
class AddPerformanceIndexes extends Migration
{
    private const INDEXES = [
        'deals'     => ['deals_deleted_created' => ['deleted_at', 'created_at']],
        'leads'     => ['leads_deleted_created' => ['deleted_at', 'created_at']],
        'companies' => ['companies_deleted_name' => ['deleted_at', 'name']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => $columns) {
                if (! $this->hasIndex($table, $name)) {
                    $this->db->query(sprintf(
                        'ALTER TABLE %s ADD INDEX %s (%s)',
                        $this->db->escapeIdentifiers($this->db->prefixTable($table)),
                        $this->db->escapeIdentifiers($name),
                        implode(', ', $this->db->escapeIdentifiers($columns)),
                    ));
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            foreach (array_keys($indexes) as $name) {
                if ($this->hasIndex($table, $name)) {
                    $this->forge->dropKey($table, $name, false);
                }
            }
        }
    }

    private function hasIndex(string $table, string $name): bool
    {
        return array_key_exists($name, $this->db->getIndexData($this->db->prefixTable($table)));
    }
}
