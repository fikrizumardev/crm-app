<?php

namespace App\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Libraries\Csv;
use App\Models\ActivityModel;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\IndustryModel;
use App\Models\LeadModel;
use App\Models\LeadSourceModel;
use App\Models\PipelineStageModel;
use App\Models\TaskModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Indonesia;

class LeadController extends BaseController
{
    private const FIELDS  = ['name', 'company_name', 'job_title', 'phone', 'email', 'source_id', 'status', 'owner_id', 'notes'];
    private const FILTERS = ['q', 'status', 'source', 'owner', 'from', 'to', 'sort', 'dir'];

    private LeadModel $leads;

    public function __construct()
    {
        $this->leads = model(LeadModel::class);
    }

    public function index(): string
    {
        $user    = $this->currentUser();
        $filters = $this->request->getGet(self::FILTERS);

        return view('leads/index', [
            'title'     => 'Lead',
            'leads'     => $this->leads->filtered($filters, $user)->paginate(25),
            'pager'     => $this->leads->pager,
            'filters'   => $filters,
            'sources'   => model(LeadSourceModel::class)->options(),
            'owners'    => $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user),
            'canExport' => $user->hasRole('admin', 'manager'),
            'user'      => $user,
        ]);
    }

    public function show(int $id): string
    {
        $user = $this->currentUser();
        $lead = $this->leads
            ->select('leads.*, lead_sources.name AS source_name, owners.name AS owner_name')
            ->join('lead_sources', 'lead_sources.id = leads.source_id', 'left')
            ->join('users AS owners', 'owners.id = leads.owner_id', 'left')
            ->findVisibleOrFail($id, $user);

        return view('leads/show', [
            'title'     => $lead['name'],
            'lead'      => $lead,
            'company'   => $lead['converted_company_id'] ? model(CompanyModel::class)->find($lead['converted_company_id']) : null,
            'contact'   => $lead['converted_contact_id'] ? model(ContactModel::class)->find($lead['converted_contact_id']) : null,
            'activities' => model(ActivityModel::class)->forRecord('lead', $id),
            'tasks'      => model(TaskModel::class)->forRecord('lead', $id, $user),
            'assignees'  => $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user),
            'canEdit'    => $this->leads->canEdit($user, $lead),
            'canDelete'  => $this->leads->canDelete($user, $lead),
        ]);
    }

    public function new(): string
    {
        $user = $this->currentUser();

        return $this->form(['status' => LeadModel::STATUS_NEW, 'owner_id' => $user->id], 'Tambah Lead');
    }

    public function create(): RedirectResponse
    {
        $user  = $this->currentUser();
        $input = $this->request->getPost(self::FIELDS);

        if ($this->request->getPost('confirm_duplicate') !== '1' && ($duplicates = service('leadService')->duplicates($input)) !== []) {
            return redirect()->to(site_url('leads/new'))->withInput()->with('duplicates', $duplicates);
        }

        try {
            $id = service('leadService')->create($input, $user);
        } catch (ValidationFailedException $e) {
            return redirect()->to(site_url('leads/new'))->withInput()->with('errors', $e->getErrors());
        }

        return redirect()->to(site_url('leads/' . $id))->with('success', 'Lead berhasil ditambahkan.');
    }

    public function edit(int $id): ResponseInterface|string
    {
        $user = $this->currentUser();
        $lead = $this->leads->findVisibleOrFail($id, $user);

        if (! $this->leads->canEdit($user, $lead)) {
            return $this->forbidden();
        }

        return $this->form($lead, 'Ubah Lead');
    }

    public function update(int $id): ResponseInterface
    {
        $user = $this->currentUser();
        $lead = $this->leads->findVisibleOrFail($id, $user);

        if (! $this->leads->canEdit($user, $lead)) {
            return $this->forbidden();
        }

        $input = $this->request->getPost(self::FIELDS);

        if ($this->request->getPost('confirm_duplicate') !== '1' && ($duplicates = service('leadService')->duplicates($input, $id)) !== []) {
            return redirect()->to(site_url("leads/{$id}/edit"))->withInput()->with('duplicates', $duplicates);
        }

        try {
            service('leadService')->update($lead, $input, $user);
        } catch (ValidationFailedException $e) {
            return redirect()->to(site_url("leads/{$id}/edit"))->withInput()->with('errors', $e->getErrors());
        }

        return redirect()->to(site_url('leads/' . $id))->with('success', 'Lead berhasil diperbarui.');
    }

    public function delete(int $id): ResponseInterface
    {
        $user = $this->currentUser();
        $lead = $this->leads->findVisibleOrFail($id, $user);

        if (! $this->leads->canDelete($user, $lead)) {
            return $this->forbidden('Lead ini tidak bisa Anda hapus.');
        }

        service('leadService')->delete($lead, $user);

        return redirect()->to(site_url('leads'))->with('success', 'Lead "' . $lead['name'] . '" berhasil dihapus.');
    }

    public function convertForm(int $id): ResponseInterface|string
    {
        $user = $this->currentUser();
        $lead = $this->leads->findVisibleOrFail($id, $user);

        if (! $this->leads->canEdit($user, $lead)) {
            return $this->forbidden();
        }

        if ($lead['status'] === LeadModel::STATUS_CONVERTED) {
            return redirect()->to(site_url('leads/' . $id))->with('warning', 'Lead ini sudah dikonversi.');
        }

        // Tawarkan perusahaan yang sudah ada bila namanya sama persis.
        $existingCompany = $lead['company_name']
            ? model(CompanyModel::class)->visibleTo($user)->where('companies.name', $lead['company_name'])->first()
            : null;

        $nameParts = preg_split('/\s+/', trim($lead['name']), 2);

        return view('leads/convert', [
            'title'           => 'Konversi Lead',
            'lead'            => $lead,
            'existingCompany' => $existingCompany,
            'firstName'       => $nameParts[0] ?? $lead['name'],
            'lastName'        => $nameParts[1] ?? '',
            'industries'      => model(IndustryModel::class)->options(),
            'provinces'       => config(Indonesia::class)->provinceOptions(),
            'stages'          => model(PipelineStageModel::class)->openStageOptions(),
        ]);
    }

    public function convert(int $id): ResponseInterface
    {
        $user = $this->currentUser();
        $lead = $this->leads->findVisibleOrFail($id, $user);

        if (! $this->leads->canEdit($user, $lead)) {
            return $this->forbidden();
        }

        try {
            $result = service('leadConversion')->convert($lead, $this->request->getPost(), $user);
        } catch (BusinessRuleException $e) {
            return redirect()->to(site_url('leads/' . $id))->with('error', $e->getMessage());
        } catch (ValidationFailedException $e) {
            return redirect()->to(site_url("leads/{$id}/convert"))->withInput()->with('errors', $e->getErrors());
        }

        $message = $result['deal_id'] === null
            ? 'Lead berhasil dikonversi menjadi perusahaan dan kontak.'
            : 'Lead berhasil dikonversi menjadi perusahaan, kontak, dan deal.';

        return redirect()->to(site_url('companies/' . $result['company_id']))->with('success', $message);
    }

    public function export(): ResponseInterface
    {
        $user = $this->currentUser();
        $rows = [];

        foreach ($this->leads->filtered($this->request->getGet(self::FILTERS), $user)->findAll() as $lead) {
            $rows[] = [
                $lead['name'],
                $lead['company_name'],
                $lead['job_title'],
                $lead['phone'] ? format_hp($lead['phone']) : '',
                $lead['email'],
                $lead['source_name'],
                LeadModel::STATUSES[$lead['status']] ?? $lead['status'],
                $lead['owner_name'],
                $lead['notes'],
                substr((string) $lead['created_at'], 0, 16),
            ];
        }

        $csv = Csv::build(
            ['Nama', 'Perusahaan', 'Jabatan', 'No. HP', 'Email', 'Sumber', 'Status', 'Owner', 'Catatan', 'Tanggal dibuat'],
            $rows,
        );

        return Csv::download($this->response, 'lead-' . date('Ymd-His') . '.csv', $csv);
    }

    public function import(): string
    {
        $this->currentUser();

        return view('leads/import', [
            'title'   => 'Impor Lead',
            'result'  => session()->getFlashdata('import_result'),
            'sources' => model(LeadSourceModel::class)->options(),
        ]);
    }

    public function importTemplate(): ResponseInterface
    {
        return Csv::download($this->response, 'template-impor-lead.csv', service('leadImport')->templateCsv());
    }

    public function importProcess(): RedirectResponse
    {
        $user = $this->currentUser();
        $file = $this->request->getFile('file');

        $valid = $this->validate([
            'file' => [
                'label' => 'File CSV',
                'rules' => 'uploaded[file]|max_size[file,2048]|ext_in[file,csv,txt]',
            ],
        ]);

        if (! $valid || $file === null) {
            return redirect()->to(site_url('leads/import'))->with('errors', $this->validator->getErrors());
        }

        try {
            $result = service('leadImport')->import($file->getTempName(), $user);
        } catch (BusinessRuleException $e) {
            return redirect()->to(site_url('leads/import'))->with('error', $e->getMessage());
        }

        $flash = $result['failed'] === [] ? 'success' : 'warning';

        return redirect()->to(site_url('leads/import'))
            ->with('import_result', $result)
            ->with($flash, sprintf('%d lead berhasil diimpor, %d baris gagal.', $result['imported'], count($result['failed'])));
    }

    /**
     * @param array<string, mixed> $lead
     */
    private function form(array $lead, string $title): string
    {
        $user = $this->currentUser();

        return view('leads/form', [
            'title'      => $title,
            'lead'       => $lead,
            'sources'    => model(LeadSourceModel::class)->options(),
            'owners'     => $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user),
            'duplicates' => session()->getFlashdata('duplicates') ?? [],
        ]);
    }
}
