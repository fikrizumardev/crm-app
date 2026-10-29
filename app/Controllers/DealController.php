<?php

namespace App\Controllers;

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Models\ActivityModel;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\DealModel;
use App\Models\LostReasonModel;
use App\Models\PipelineStageModel;
use App\Models\QuotationModel;
use App\Models\TaskModel;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

class DealController extends BaseController
{
    private const FIELDS  = ['title', 'company_id', 'contact_id', 'amount', 'stage_id', 'probability', 'expected_close_date', 'lost_reason_id', 'owner_id'];
    private const FILTERS = ['q', 'stage', 'status', 'owner', 'close_from', 'close_to', 'min_amount', 'sort', 'dir'];

    /**
     * Maksimal kartu per kolom Kanban; sisanya dibuka lewat tampilan tabel.
     */
    private const BOARD_LIMIT = 50;

    private DealModel $deals;

    public function __construct()
    {
        $this->deals = model(DealModel::class);
    }

    public function index(): string
    {
        $user    = $this->currentUser();
        $filters = $this->request->getGet(self::FILTERS);

        return view('deals/index', [
            'title'   => 'Deal',
            'deals'   => $this->deals->filtered($filters, $user)->paginate(25),
            'pager'   => $this->deals->pager,
            'filters' => $filters,
            'stages'  => array_column(model(PipelineStageModel::class)->ordered(), 'name', 'id'),
            'owners'  => $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user),
            'user'    => $user,
        ]);
    }

    public function board(): string
    {
        $user    = $this->currentUser();
        $ownerId = ! $user->isSales() && ctype_digit((string) $this->request->getGet('owner')) ? (int) $this->request->getGet('owner') : null;
        $totals  = $this->deals->boardTotals($user, $ownerId);
        $columns = [];

        foreach (model(PipelineStageModel::class)->ordered() as $stage) {
            $columns[$stage['id']] = [
                'stage' => $stage,
                'deals' => $this->deals->forBoardStage($user, $ownerId, (int) $stage['id'], self::BOARD_LIMIT),
                'count' => $totals[(int) $stage['id']]['count'] ?? 0,
                'sum'   => $totals[(int) $stage['id']]['sum'] ?? 0.0,
            ];
        }

        return view('deals/board', [
            'title'       => 'Pipeline',
            'columns'     => $columns,
            'lostReasons' => model(LostReasonModel::class)->options(),
            'owners'      => $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user),
            'ownerId'     => $ownerId,
            'user'        => $user,
        ]);
    }

    public function show(int $id): string
    {
        $user = $this->currentUser();
        $deal = $this->deals->findDetailOrFail($id, $user);

        $activities = model(ActivityModel::class)->forRecord('deal', $id);

        return view('deals/show', [
            'title'       => $deal['title'],
            'deal'        => $deal,
            'history'     => $this->deals->stageHistory($id),
            'activities'  => $activities,
            'tasks'       => model(TaskModel::class)->forRecord('deal', $id, $user),
            'quotations'  => model(QuotationModel::class)->forDeal($id, $user),
            'stages'      => model(PipelineStageModel::class)->ordered(),
            'lostReasons' => model(LostReasonModel::class)->options(),
            'assignees'   => $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user),
            'canEdit'     => $this->deals->canEdit($user, $deal),
            'canDelete'   => $this->deals->canDelete($user, $deal),
        ]);
    }

    public function new(): string
    {
        $user    = $this->currentUser();
        $stages  = model(PipelineStageModel::class)->openStages();
        $company = null;

        if (ctype_digit((string) $this->request->getGet('company'))) {
            $company = model(CompanyModel::class)->visibleTo($user)->find((int) $this->request->getGet('company'));
        }

        return $this->form([
            'owner_id'            => $user->id,
            'stage_id'            => $stages[0]['id'] ?? null,
            'probability'         => $stages[0]['probability'] ?? null,
            'company_id'          => $company['id'] ?? null,
            'company_name'        => $company['name'] ?? null,
            'expected_close_date' => date('Y-m-d', strtotime('last day of next month')),
        ], 'Tambah Deal');
    }

    public function create(): RedirectResponse
    {
        try {
            $id = service('dealService')->create($this->request->getPost(self::FIELDS), $this->currentUser());
        } catch (ValidationFailedException $e) {
            return redirect()->to(site_url('deals/new'))->withInput()->with('errors', $e->getErrors());
        }

        return redirect()->to(site_url('deals/' . $id))->with('success', 'Deal berhasil ditambahkan.');
    }

    public function edit(int $id): ResponseInterface|string
    {
        $user = $this->currentUser();
        $deal = $this->deals->findDetailOrFail($id, $user);

        if (! $this->deals->canEdit($user, $deal)) {
            return $this->forbidden();
        }

        return $this->form($deal, 'Ubah Deal');
    }

    public function update(int $id): ResponseInterface
    {
        $user = $this->currentUser();
        $deal = $this->deals->findVisibleOrFail($id, $user);

        if (! $this->deals->canEdit($user, $deal)) {
            return $this->forbidden();
        }

        try {
            service('dealService')->update($deal, $this->request->getPost(self::FIELDS), $user);
        } catch (ValidationFailedException $e) {
            return redirect()->to(site_url("deals/{$id}/edit"))->withInput()->with('errors', $e->getErrors());
        }

        return redirect()->to(site_url('deals/' . $id))->with('success', 'Deal berhasil diperbarui.');
    }

    public function delete(int $id): ResponseInterface
    {
        $user = $this->currentUser();
        $deal = $this->deals->withRelations()->findVisibleOrFail($id, $user);

        if (! $this->deals->canDelete($user, $deal)) {
            return $this->forbidden('Deal yang sudah Won hanya bisa dihapus oleh manager atau admin.');
        }

        service('dealService')->delete($deal, $user);

        return redirect()->to(site_url('deals'))->with('success', 'Deal "' . $deal['title'] . '" berhasil dihapus.');
    }

    /**
     * Pindah tahap dari Kanban (JSON) atau dari halaman detail (form).
     */
    public function stage(int $id): ResponseInterface
    {
        $user = $this->currentUser();

        try {
            $deal = $this->deals->findVisibleOrFail($id, $user);
        } catch (PageNotFoundException) {
            return $this->wantsJson()
                ? $this->json(404, ['message' => 'Deal tidak ditemukan.'])
                : throw PageNotFoundException::forPageNotFound();
        }

        $lostReason = ctype_digit((string) $this->request->getPost('lost_reason_id')) ? (int) $this->request->getPost('lost_reason_id') : null;

        try {
            $updated = service('dealPipeline')->moveToStage($deal, (int) $this->request->getPost('stage_id'), $user, $lostReason);
        } catch (BusinessRuleException $e) {
            return $this->wantsJson()
                ? $this->json(403, ['message' => $e->getMessage()])
                : redirect()->to(site_url('deals/' . $id))->with('error', $e->getMessage());
        } catch (ValidationFailedException $e) {
            $message = (string) (array_values($e->getErrors())[0] ?? $e->getMessage());

            return $this->wantsJson()
                ? $this->json(422, ['message' => $message, 'errors' => $e->getErrors()])
                : redirect()->to(site_url('deals/' . $id))->with('error', $message);
        }

        $message = sprintf('Deal "%s" dipindahkan ke tahap %s.', $updated['title'], $updated['stage_name']);

        if (! $this->wantsJson()) {
            return redirect()->to(site_url('deals/' . $id))->with('success', $message);
        }

        return $this->json(200, [
            'message' => $message,
            'deal'    => [
                'id'          => (int) $updated['id'],
                'stage_id'    => (int) $updated['stage_id'],
                'stage_name'  => $updated['stage_name'],
                'probability' => (int) $updated['probability'],
                'amount'      => (float) $updated['amount'],
            ],
        ]);
    }

    /**
     * Respons JSON yang selalu menyertakan token CSRF terbaru (token diganti setiap POST).
     *
     * @param array<string, mixed> $data
     */
    private function json(int $status, array $data): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON($data + ['ok' => $status < 300, 'csrf' => csrf_hash()]);
    }

    /**
     * @param array<string, mixed> $deal
     */
    private function form(array $deal, string $title): string
    {
        $user = $this->currentUser();

        // Setelah validasi gagal, daftar kontak mengikuti perusahaan yang terakhir dipilih.
        $companyId = (int) (old('company_id', $deal['company_id'] ?? 0, false) ?: 0);

        return view('deals/form', [
            'title'       => $title,
            'deal'        => $deal,
            'stages'      => model(PipelineStageModel::class)->ordered(),
            'lostReasons' => model(LostReasonModel::class)->options(),
            'contacts'    => $companyId > 0 ? $this->contactOptions($companyId) : [],
            'owners'      => $user->isSales() ? [] : model(UserModel::class)->ownerOptions($user),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function contactOptions(int $companyId): array
    {
        $options = [];

        foreach (model(ContactModel::class)->forCompany($companyId, $this->currentUser()) as $contact) {
            $options[(int) $contact['id']] = ContactModel::fullName($contact);
        }

        return $options;
    }
}
