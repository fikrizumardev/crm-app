<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model dasar untuk master data sederhana (id + name).
 */
abstract class MasterDataModel extends Model
{
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['name'];

    /**
     * Pilihan dropdown [id => name], urut nama.
     *
     * @return array<int, string>
     */
    public function options(): array
    {
        $rows = $this->select('id, name')->orderBy('name')->findAll();

        return array_column($rows, 'name', 'id');
    }

    /**
     * Cari ID berdasarkan nama (collation utf8mb4_unicode_ci tidak membedakan huruf besar/kecil).
     */
    public function idByName(string $name): ?int
    {
        $row = $this->where('name', trim($name))->first();

        return $row === null ? null : (int) $row['id'];
    }
}
