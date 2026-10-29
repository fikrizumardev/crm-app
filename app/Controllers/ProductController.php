<?php

namespace App\Controllers;

use App\Models\ProductModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Katalog produk. Semua peran bisa melihat; tambah/ubah/hapus khusus admin (filter rute).
 */
class ProductController extends BaseController
{
    private ProductModel $products;

    public function __construct()
    {
        $this->products = model(ProductModel::class);
    }

    public function index(): string
    {
        $filters = $this->request->getGet(['q', 'status', 'sort', 'dir']);

        return view('products/index', [
            'title'     => 'Produk',
            'products'  => $this->products->filtered($filters)->paginate(25),
            'pager'     => $this->products->pager,
            'filters'   => $filters,
            'canManage' => $this->currentUser()->isAdmin(),
        ]);
    }

    public function new(): string
    {
        return view('products/form', ['title' => 'Tambah Produk', 'product' => ['is_active' => 1, 'unit' => 'unit']]);
    }

    public function create(): RedirectResponse
    {
        $data = $this->input();
        $id   = $this->products->insert($data);

        if ($id === false) {
            return redirect()->to(site_url('products/new'))->withInput()->with('errors', $this->products->errors());
        }

        service('auditLogger')->log('create', 'product', $id, null, $data);

        return redirect()->to(site_url('products'))->with('success', 'Produk "' . $data['name'] . '" berhasil ditambahkan.');
    }

    public function edit(int $id): string
    {
        return view('products/form', ['title' => 'Ubah Produk', 'product' => $this->findOrFail($id)]);
    }

    public function update(int $id): RedirectResponse
    {
        $product = $this->findOrFail($id);
        $data    = $this->input();

        if (! $this->products->update($id, ['id' => $id] + $data)) {
            return redirect()->to(site_url("products/{$id}/edit"))->withInput()->with('errors', $this->products->errors());
        }

        service('auditLogger')->log('update', 'product', $id, $product, $this->products->find($id));

        return redirect()->to(site_url('products'))->with('success', 'Produk "' . $data['name'] . '" berhasil diperbarui.');
    }

    public function delete(int $id): RedirectResponse
    {
        $product = $this->findOrFail($id);
        $this->products->delete($id);
        service('auditLogger')->log('delete', 'product', $id, $product, null);

        return redirect()->to(site_url('products'))->with('success', 'Produk "' . $product['name'] . '" dihapus. Penawaran lama yang memakainya tidak berubah.');
    }

    /**
     * @return array<string, mixed>
     */
    private function input(): array
    {
        return [
            'sku'       => strtoupper(trim((string) $this->request->getPost('sku'))),
            'name'      => trim((string) $this->request->getPost('name')),
            'unit'      => trim((string) $this->request->getPost('unit')),
            'price'     => parse_rupiah($this->request->getPost('price')),
            'is_active' => $this->request->getPost('is_active') === '1' ? '1' : '0',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function findOrFail(int $id): array
    {
        $product = $this->products->find($id);

        if ($product === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $product;
    }
}
