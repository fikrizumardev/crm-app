<?php

namespace App\Models;

use CodeIgniter\Model;

class QuotationItemModel extends Model
{
    protected $table         = 'quotation_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['quotation_id', 'product_id', 'description', 'qty', 'unit_price', 'discount_pct', 'line_total'];

    /**
     * Item penawaran beserta SKU & satuan produk.
     *
     * @return list<array<string, mixed>>
     */
    public function forQuotation(int $quotationId): array
    {
        return $this->select('quotation_items.*, products.sku, products.unit')
            ->join('products', 'products.id = quotation_items.product_id', 'left')
            ->where('quotation_items.quotation_id', $quotationId)
            ->orderBy('quotation_items.id')
            ->findAll();
    }
}
