<?php

namespace App\Controllers;

use App\Exceptions\ValidationFailedException;
use App\Libraries\Csv;
use App\Models\ActivityModel;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\DealModel;
use App\Models\TaskModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class ContactController extends BaseController
{
    private const FIELDS  = ['company_id', 'first_name', 'last_name', 'job_title', 'phone', 'whatsapp', 'email', 'birth_date', 'owner_id'];
    private const FILTERS = ['q', 'company', 'owner', 'sort', 'dir'];

    private ContactModel $contacts;

    public function __construct()
    {
        $this->contacts = model(ContactModel::class);
    }

    public function index(): string
    {
        $user    = $this->currentUser();
        $filters = $this->request->getGet(self::FILTERS);
        $company = ctype_digit((string) ($filters['company'] ?? '')) ? model(CompanyModel::class)->visibleTo($user)->find((int) $filters['company']) : null;

        return view('contacts/index', [
            'title'     => 'Kontak',
            'contacts'  => $this->contacts->filtered($filters, $user)->paginate(25),
            'pager'     => $this->contacts->pager,
            'filters'   => $filters,
            'company'   => $company,
            'owners'    => $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user),
            'canExport' => $user->hasRole('admin', 'manager'),
            'user'      => $user,
        ]);
    }

    /**
     * JSON kontak milik satu perusahaan (untuk pilihan kontak di form deal).
     */
    public function options(): ResponseInterface
    {
        $companyId = (int) $this->request->getGet('company');
        $result    = [];

        if ($companyId > 0) {
            foreach ($this->contacts->forCompany($companyId, $this->currentUser()) as $contact) {
                $result[] = ['id' => (int) $contact['id'], 'name' => ContactModel::fullName($contact), 'job_title' => $contact['job_title']];
            }
        }

        return $this->response->setJSON($result);
    }

    public function show(int $id): string
    {
        $user    = $this->currentUser();
        $contact = $this->contacts->findDetailOrFail($id, $user);

        return view('contacts/show', [
            'title'      => ContactModel::fullName($contact),
            'contact'    => $contact,
            'activities' => model(ActivityModel::class)->forRecord('contact', $id),
            'tasks'      => model(TaskModel::class)->forRecord('contact', $id, $user),
            'deals'      => model(DealModel::class)->forContact($id, $user),
            'assignees'  => $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user),
            'canEdit'    => $this->contacts->canEdit($user, $contact),
            'canDelete'  => $this->contacts->canDelete($user, $contact),
        ]);
    }

    public function new(): string
    {
        $user    = $this->currentUser();
        $company = null;

        // Bisa dibuka dari halaman perusahaan: /contacts/new?company=ID
        if (ctype_digit((string) $this->request->getGet('company'))) {
            $company = model(CompanyModel::class)->visibleTo($user)->find((int) $this->request->getGet('company'));
        }

        return $this->form([
            'owner_id'     => $user->id,
            'company_id'   => $company['id'] ?? null,
            'company_name' => $company['name'] ?? null,
        ], 'Tambah Kontak');
    }

    public function create(): RedirectResponse
    {
        try {
            $id = service('contactService')->create($this->request->getPost(self::FIELDS), $this->currentUser());
        } catch (ValidationFailedException $e) {
            return redirect()->to(site_url('contacts/new'))->withInput()->with('errors', $e->getErrors());
        }

        return redirect()->to(site_url('contacts/' . $id))->with('success', 'Kontak berhasil ditambahkan.');
    }

    public function edit(int $id): ResponseInterface|string
    {
        $user    = $this->currentUser();
        $contact = $this->contacts->findDetailOrFail($id, $user);

        if (! $this->contacts->canEdit($user, $contact)) {
            return $this->forbidden();
        }

        return $this->form($contact, 'Ubah Kontak');
    }

    public function update(int $id): ResponseInterface
    {
        $user    = $this->currentUser();
        $contact = $this->contacts->findVisibleOrFail($id, $user);

        if (! $this->contacts->canEdit($user, $contact)) {
            return $this->forbidden();
        }

        try {
            service('contactService')->update($contact, $this->request->getPost(self::FIELDS), $user);
        } catch (ValidationFailedException $e) {
            return redirect()->to(site_url("contacts/{$id}/edit"))->withInput()->with('errors', $e->getErrors());
        }

        return redirect()->to(site_url('contacts/' . $id))->with('success', 'Kontak berhasil diperbarui.');
    }

    public function delete(int $id): ResponseInterface
    {
        $user    = $this->currentUser();
        $contact = $this->contacts->findVisibleOrFail($id, $user);

        if (! $this->contacts->canDelete($user, $contact)) {
            return $this->forbidden('Kontak ini tidak bisa Anda hapus.');
        }

        service('contactService')->delete($contact, $user);

        return redirect()->to(site_url('contacts'))->with('success', 'Kontak "' . ContactModel::fullName($contact) . '" berhasil dihapus.');
    }

    public function export(): ResponseInterface
    {
        $rows = [];

        foreach ($this->contacts->filtered($this->request->getGet(self::FILTERS), $this->currentUser())->findAll() as $contact) {
            $rows[] = [
                $contact['first_name'],
                $contact['last_name'],
                $contact['job_title'],
                $contact['company_name'],
                $contact['phone'] ? format_hp($contact['phone']) : '',
                $contact['whatsapp'] ? format_hp($contact['whatsapp']) : '',
                $contact['email'],
                $contact['birth_date'],
                $contact['owner_name'],
                substr((string) $contact['created_at'], 0, 16),
            ];
        }

        $csv = Csv::build(
            ['Nama Depan', 'Nama Belakang', 'Jabatan', 'Perusahaan', 'No. HP', 'WhatsApp', 'Email', 'Tanggal Lahir', 'Owner', 'Tanggal dibuat'],
            $rows,
        );

        return Csv::download($this->response, 'kontak-' . date('Ymd-His') . '.csv', $csv);
    }

    /**
     * @param array<string, mixed> $contact
     */
    private function form(array $contact, string $title): string
    {
        $user = $this->currentUser();

        return view('contacts/form', [
            'title'   => $title,
            'contact' => $contact,
            'owners'  => $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user),
        ]);
    }
}
