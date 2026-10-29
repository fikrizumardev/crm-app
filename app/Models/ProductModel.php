<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Katalog produk/layanan (PRD §4 M8).
 */
class ProductModel extends Model
{
    protected $table          = 'products';
    protected $primaryKey     = 'id';
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = ['sku', 'name', 'unit', 'price', 'is_active'];
    protected $validationRules = [
        'id'        => 'permit_empty|is_natural_no_zero',
        'sku'       => ['label' => 'SKU', 'rules' => 'required|regex_match[/^[A-Z0-9][A-Z0-9\-_.]{1,49}$/]|is_unique[products.sku,id,{id}]'],
        'name'      => ['label' => 'Nama produk', 'rules' => 'required|max_length[200]'],
        'unit'      => ['label' => 'Satuan', 'rules' => 'required|max_length[30]'],
        'price'     => ['label' => 'Harga', 'rules' => 'required|decimal|greater_than_equal_to[0]|less_than[10000000000000]'],
        'is_active' => ['label' => 'Status', 'rules' => 'required|in_list[0,1]'],
    ];
    protected $validationMessages = [
        'sku' => [
            'regex_match' => 'SKU hanya boleh huruf besar, angka, titik, garis bawah, atau tanda hubung (2–50 karakter).',
            'is_unique'   => 'SKU sudah dipakai produk lain.',
        ],
    ];

    private const SORTABLE = [
        'sku'   => 'products.sku',
        'name'  => 'products.name',
        'price' => 'products.price',
        'unit'  => 'products.unit',
    ];

    /**
     * Query daftar produk. Filter: q, status (active|inactive), sort, dir.
     *
     * @param array<string, mixed> $filters
     *
     * @return $this
     */
    public function filtered(array $filters): static
    {
        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $this->groupStart()->like('products.sku', $q)->orLike('products.name', $q)->groupEnd();
        }

        if (in_array($filters['status'] ?? '', ['active', 'inactive'], true)) {
            $this->where('products.is_active', $filters['status'] === 'active' ? 1 : 0);
        }

        $column = self::SORTABLE[$filters['sort'] ?? ''] ?? 'products.name';
        $dir    = ($filters['dir'] ?? '') === 'desc' ? 'desc' : 'asc';

        return $this->orderBy($column, $dir)->orderBy('products.id', $dir);
    }

    /**
     * Produk aktif untuk pilihan di form penawaran.
     *
     * @return list<array{id: int, sku: string, name: string, unit: string, price: float}>
     */
    public function activeOptions(): array
    {
        return array_map(static fn (array $row): array => [
            'id'    => (int) $row['id'],
            'sku'   => $row['sku'],
            'name'  => $row['name'],
            'unit'  => $row['unit'],
            'price' => (float) $row['price'],
        ], $this->where('is_active', 1)->orderBy('name')->findAll());
    }
}
