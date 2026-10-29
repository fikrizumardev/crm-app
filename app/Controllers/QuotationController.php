<?php

namespace App\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\DealModel;
use App\Models\ProductModel;
use App\Models\QuotationItemModel;
use App\Models\QuotationModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Penawaran harga dari deal (PRD §4 M8).
 */
class QuotationController extends BaseController
{
    private const FIELDS = ['issue_date', 'valid_until', 'notes', 'items'];

    private QuotationModel $quotations;

    public function __construct()
    {
        $this->quotations = model(QuotationModel::class);
    }

    public function index(): string
    {
        $user    = $this->currentUser();
        $filters = $this->request->getGet(['q', 'status', 'sort', 'dir']);

        return view('quotations/index', [
            'title'      => 'Penawaran',
            'quotations' => $this->quotations->filtered($filters, $user)->paginate(25),
            'pager'      => $this->quotations->pager,
            'filters'    => $filters,
            'user'       => $user,
        ]);
    }

    public function show(int $id): string
    {
        $user      = $this->currentUser();
        $quotation = $this->quotations->findVisibleOrFail($id, $user);

        return view('quotations/show', [
            'title'     => $quotation['number'],
            'quotation' => $quotation,
            'items'     => model(QuotationItemModel::class)->forQuotation($id),
            'canEdit'   => $this->quotations->canEdit($user, $quotation),
            'canDelete' => $this->quotations->canDelete($user, $quotation),
        ]);
    }

    /**
     * Form penawaran baru; wajib dari deal: /quotations/new?deal=ID
     */
    public function new(): ResponseInterface|string
    {
        $user = $this->currentUser();
        $deal = model(DealModel::class)->findDetailOrFail((string) $this->request->getGet('deal'), $user);

        if (! model(DealModel::class)->canEdit($user, $deal)) {
            return $this->forbidden();
        }

        return $this->form($deal, [
            'issue_date'  => date('Y-m-d'),
            'valid_until' => date('Y-m-d', strtotime('+14 days')),
            'tax_rate'    => app_setting('tax_rate', '11'),
            'notes'       => "Harga belum termasuk biaya pengiriman di luar kota.\nPembayaran 50% di muka, 50% setelah serah terima.",
        ], [], 'Buat Penawaran');
    }

    public function create(): ResponseInterface
    {
        $user = $this->currentUser();
        $deal = model(DealModel::class)->findVisibleOrFail((string) $this->request->getPost('deal_id'), $user);

        try {
            $id = service('quotationService')->create($deal, $this->request->getPost(self::FIELDS), $user);
        } catch (ValidationFailedException $e) {
            return redirect()->to(site_url('quotations/new?deal=' . $deal['id']))->withInput()->with('errors', $e->getErrors());
        } catch (BusinessRuleException $e) {
            return $this->forbidden($e->getMessage());
        }

        return redirect()->to(site_url('quotations/' . $id))->with('success', 'Penawaran berhasil dibuat.');
    }

    public function edit(int $id): ResponseInterface|string
    {
        $user      = $this->currentUser();
        $quotation = $this->quotations->findVisibleOrFail($id, $user);

        try {
            service('quotationService')->assertEditable($quotation, $user);
        } catch (BusinessRuleException $e) {
            return redirect()->to(site_url('quotations/' . $id))->with('error', $e->getMessage());
        }

        $deal = model(DealModel::class)->withRelations()->find($quotation['deal_id']);

        return $this->form($deal, $quotation, model(QuotationItemModel::class)->forQuotation($id), 'Ubah Penawaran ' . $quotation['number']);
    }

    public function update(int $id): RedirectResponse
    {
        $user      = $this->currentUser();
        $quotation = $this->quotations->findVisibleOrFail($id, $user);

        try {
            service('quotationService')->update($quotation, $this->request->getPost(self::FIELDS), $user);
        } catch (ValidationFailedException $e) {
            return redirect()->to(site_url("quotations/{$id}/edit"))->withInput()->with('errors', $e->getErrors());
        } catch (BusinessRuleException $e) {
            return redirect()->to(site_url('quotations/' . $id))->with('error', $e->getMessage());
        }

        return redirect()->to(site_url('quotations/' . $id))->with('success', 'Penawaran berhasil diperbarui.');
    }

    public function status(int $id): RedirectResponse
    {
        $user      = $this->currentUser();
        $quotation = $this->quotations->findVisibleOrFail($id, $user);
        $status    = (string) $this->request->getPost('status');

        try {
            service('quotationService')->setStatus($quotation, $status, $user);
        } catch (BusinessRuleException|ValidationFailedException $e) {
            $message = $e instanceof ValidationFailedException ? (string) array_values($e->getErrors())[0] : $e->getMessage();

            return redirect()->to(site_url('quotations/' . $id))->with('error', $message);
        }

        $label = QuotationModel::STATUSES[$status]['label'];

        return redirect()->to(site_url('quotations/' . $id))->with('success', 'Status penawaran diubah menjadi ' . $label . '.');
    }

    public function delete(int $id): ResponseInterface
    {
        $user      = $this->currentUser();
        $quotation = $this->quotations->findVisibleOrFail($id, $user);

        try {
            service('quotationService')->delete($quotation, $user);
        } catch (BusinessRuleException $e) {
            return $this->forbidden($e->getMessage());
        }

        return redirect()->to(site_url('deals/' . $quotation['deal_id']))->with('success', 'Penawaran ' . $quotation['number'] . ' dihapus.');
    }

    /**
     * Halaman siap cetak; simpan PDF lewat dialog print browser.
     */
    public function print(int $id): string
    {
        $user      = $this->currentUser();
        $quotation = $this->quotations->findVisibleOrFail($id, $user);
        $deal      = model(DealModel::class)->withRelations()->find($quotation['deal_id']);

        return view('quotations/print', [
            'quotation' => $quotation,
            'items'     => model(QuotationItemModel::class)->forQuotation($id),
            'deal'      => $deal,
            'company'   => $deal['company_id'] ? model(CompanyModel::class)->find($deal['company_id']) : null,
            'contact'   => $deal['contact_id'] ? model(ContactModel::class)->find($deal['contact_id']) : null,
            'settings'  => model(SettingModel::class)->allValues(),
        ]);
    }

    /**
     * @param array<string, mixed>       $deal
     * @param array<string, mixed>       $quotation
     * @param list<array<string, mixed>> $items
     */
    private function form(array $deal, array $quotation, array $items, string $title): string
    {
        return view('quotations/form', [
            'title'     => $title,
            'deal'      => $deal,
            'quotation' => $quotation,
            'items'     => $items,
            'products'  => model(ProductModel::class)->activeOptions(),
        ]);
    }
}
