<?php

namespace App\Controllers;

use App\Exceptions\ValidationFailedException;
use App\Libraries\Csv;
use App\Models\ActivityModel;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\DealModel;
use App\Models\IndustryModel;
use App\Models\TaskModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Indonesia;

class CompanyController extends BaseController
{
    private const FIELDS  = ['name', 'industry_id', 'npwp', 'phone', 'website', 'address', 'city', 'province', 'employee_count', 'owner_id'];
    private const FILTERS = ['q', 'industry', 'province', 'owner', 'sort', 'dir'];

    private CompanyModel $companies;

    public function __construct()
    {
        $this->companies = model(CompanyModel::class);
    }

    public function index(): string
    {
        $user    = $this->currentUser();
        $filters = $this->request->getGet(self::FILTERS);

        return view('companies/index', [
            'title'      => 'Perusahaan',
            'companies'  => $this->companies->filtered($filters, $user)->paginate(25),
            'pager'      => $this->companies->pager,
            'filters'    => $filters,
            'industries' => model(IndustryModel::class)->options(),
            'provinces'  => config(Indonesia::class)->provinceOptions(),
            'owners'     => $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user),
            'canExport'  => $user->hasRole('admin', 'manager'),
            'user'       => $user,
        ]);
    }

    /**
     * JSON untuk combobox pemilihan perusahaan.
     */
    public function search(): ResponseInterface
    {
        $results = $this->companies->search((string) $this->request->getGet('q'), $this->currentUser());

        return $this->response->setJSON($results);
    }

    public function show(int $id): string
    {
        $user    = $this->currentUser();
        $company = $this->companies->findDetailOrFail($id, $user);

        return view('companies/show', [
            'title'     => $company['name'],
            'company'   => $company,
            'contacts'  => model(ContactModel::class)->forCompany($id, $user),
            'deals'      => model(DealModel::class)->forCompany($id, $user),
            'activities' => model(ActivityModel::class)->forCompany($id),
            'notes'      => model(ActivityModel::class)->forRecord('company', $id, 'note'),
            'tasks'      => model(TaskModel::class)->forRecord('company', $id, $user),
            'assignees'  => $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user),
            'canEdit'   => $this->companies->canEdit($user, $company),
            'canDelete' => $this->companies->canDelete($user, $company),
        ]);
    }

    public function new(): string
    {
        return $this->form(['owner_id' => $this->currentUser()->id], 'Tambah Perusahaan');
    }

    public function create(): RedirectResponse
    {
        try {
            $id = service('companyService')->create($this->request->getPost(self::FIELDS), $this->currentUser());
        } catch (ValidationFailedException $e) {
            return redirect()->to(site_url('companies/new'))->withInput()->with('errors', $e->getErrors());
        }

        return redirect()->to(site_url('companies/' . $id))->with('success', 'Perusahaan berhasil ditambahkan.');
    }

    public function edit(int $id): ResponseInterface|string
    {
        $user    = $this->currentUser();
        $company = $this->companies->findVisibleOrFail($id, $user);

        if (! $this->companies->canEdit($user, $company)) {
            return $this->forbidden();
        }

        return $this->form($company, 'Ubah Perusahaan');
    }

    public function update(int $id): ResponseInterface
    {
        $user    = $this->currentUser();
        $company = $this->companies->findVisibleOrFail($id, $user);

        if (! $this->companies->canEdit($user, $company)) {
            return $this->forbidden();
        }

        try {
            service('companyService')->update($company, $this->request->getPost(self::FIELDS), $user);
        } catch (ValidationFailedException $e) {
            return redirect()->to(site_url("companies/{$id}/edit"))->withInput()->with('errors', $e->getErrors());
        }

        return redirect()->to(site_url('companies/' . $id))->with('success', 'Data perusahaan berhasil diperbarui.');
    }

    public function delete(int $id): ResponseInterface
    {
        $user    = $this->currentUser();
        $company = $this->companies->findVisibleOrFail($id, $user);

        if (! $this->companies->canDelete($user, $company)) {
            return $this->forbidden('Perusahaan ini tidak bisa Anda hapus.');
        }

        service('companyService')->delete($company, $user);

        return redirect()->to(site_url('companies'))->with('success', 'Perusahaan "' . $company['name'] . '" berhasil dihapus.');
    }

    public function export(): ResponseInterface
    {
        $rows = [];

        foreach ($this->companies->filtered($this->request->getGet(self::FILTERS), $this->currentUser())->findAll() as $company) {
            $rows[] = [
                $company['name'],
                $company['industry_name'],
                $company['npwp'] ? format_npwp($company['npwp']) : '',
                $company['phone'] ? format_hp($company['phone']) : '',
                $company['website'],
                $company['address'],
                $company['city'],
                $company['province'],
                $company['employee_count'],
                $company['contact_count'],
                $company['owner_name'],
                substr((string) $company['created_at'], 0, 16),
            ];
        }

        $csv = Csv::build(
            ['Nama Perusahaan', 'Industri', 'NPWP', 'Telepon', 'Website', 'Alamat', 'Kota', 'Provinsi', 'Jumlah Karyawan', 'Jumlah Kontak', 'Owner', 'Tanggal dibuat'],
            $rows,
        );

        return Csv::download($this->response, 'perusahaan-' . date('Ymd-His') . '.csv', $csv);
    }

    /**
     * @param array<string, mixed> $company
     */
    private function form(array $company, string $title): string
    {
        $user = $this->currentUser();

        return view('companies/form', [
            'title'      => $title,
            'company'    => $company,
            'industries' => model(IndustryModel::class)->options(),
            'provinces'  => config(Indonesia::class)->provinceOptions(),
            'owners'     => $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user),
        ]);
    }
}
