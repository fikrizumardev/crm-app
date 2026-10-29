<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\ValidationFailedException;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\DealModel;
use App\Models\DealStageHistoryModel;
use App\Models\PipelineStageModel;
use App\Models\UserModel;
use CodeIgniter\I18n\Time;
use Throwable;

class DealService extends RecordService
{
    public function __construct(
        DealModel $model,
        AuditLogger $audit,
        UserModel $users,
        private readonly DealPipelineService $pipeline,
        private readonly PipelineStageModel $stages,
        private readonly DealStageHistoryModel $history,
    ) {
        parent::__construct($model, $audit, $users);
    }

    protected function entity(): string
    {
        return 'deal';
    }

    protected function entityLabel(): string
    {
        return 'deal';
    }

    protected function path(): string
    {
        return 'deals';
    }

    protected function recordName(array $record): string
    {
        return (string) $record['title'];
    }

    protected function prepare(array $input, User $actor, ?array $existing): array
    {
        $companyId = $this->id($input['company_id'] ?? null);
        $contactId = $this->id($input['contact_id'] ?? null);

        if ($companyId !== null && $companyId !== (int) ($existing['company_id'] ?? 0)
            && model(CompanyModel::class)->visibleTo($actor)->find($companyId) === null) {
            throw new ValidationFailedException(['company_id' => 'Perusahaan tidak ditemukan.']);
        }

        if ($contactId !== null) {
            $contact = $contactId === (int) ($existing['contact_id'] ?? 0)
                ? model(ContactModel::class)->find($contactId)
                : model(ContactModel::class)->visibleTo($actor)->find($contactId);

            if ($contact === null) {
                throw new ValidationFailedException(['contact_id' => 'Kontak tidak ditemukan.']);
            }

            if ($companyId !== null && (int) $contact['company_id'] !== $companyId) {
                throw new ValidationFailedException(['contact_id' => 'Kontak bukan bagian dari perusahaan yang dipilih.']);
            }
        }

        $probability = trim((string) ($input['probability'] ?? ''));

        return [
            'title'               => $this->text($input['title'] ?? null),
            'company_id'          => $companyId,
            'contact_id'          => $contactId,
            'stage_id'            => $this->id($input['stage_id'] ?? null),
            'amount'              => parse_rupiah($input['amount'] ?? null),
            'probability'         => $probability === '' ? null : $probability,
            'expected_close_date' => $this->text($input['expected_close_date'] ?? null),
            'lost_reason_id'      => $this->id($input['lost_reason_id'] ?? null),
        ];
    }

    public function create(array $input, User $actor): int
    {
        $data             = $this->prepare($input, $actor, null);
        $data['owner_id'] = $this->resolveOwner($input['owner_id'] ?? null, $actor, null);
        $stage            = $this->stages->find($data['stage_id'] ?? 0);

        if ($stage === null) {
            throw new ValidationFailedException(['stage_id' => 'Pilih tahap pipeline.']);
        }

        $probability = $this->probability($data['probability']);
        $data        = array_merge($data, $this->pipeline->stageRules($stage, $data['lost_reason_id'], $probability));

        $db = db_connect();
        $db->transBegin();

        try {
            $id = $this->model->insert($data);

            if ($id === false) {
                throw new ValidationFailedException($this->model->errors());
            }

            $this->history->insert([
                'deal_id'       => $id,
                'from_stage_id' => null,
                'to_stage_id'   => $stage['id'],
                'changed_by'    => $actor->id,
                'changed_at'    => Time::now()->toDateTimeString(),
            ]);

            $this->audit->log('create', 'deal', $id, null, $this->model->find($id));
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }

        return (int) $id;
    }

    /**
     * Ubah data deal; perubahan tahap diteruskan ke DealPipelineService agar riwayat tercatat.
     */
    public function update(array $record, array $input, User $actor): void
    {
        $data             = $this->prepare($input, $actor, $record);
        $data['owner_id'] = $this->resolveOwner($input['owner_id'] ?? null, $actor, (int) $record['owner_id']);
        $newStageId       = $data['stage_id'] ?? (int) $record['stage_id'];
        $lostReasonId     = $data['lost_reason_id'];
        $probability      = $this->probability($data['probability']);

        unset($data['stage_id'], $data['lost_reason_id'], $data['probability']);

        $stageChanged = $newStageId !== (int) $record['stage_id'];

        // Probabilitas yang tidak disentuh user saat tahap berubah mengikuti default tahap baru.
        if ($stageChanged && $probability === (int) $record['probability']) {
            $probability = null;
        }

        $db = db_connect();
        $db->transBegin();

        try {
            if (! $this->model->update($record['id'], $data)) {
                throw new ValidationFailedException($this->model->errors());
            }

            $current = $this->model->find($record['id']);
            $this->pipeline->moveToStage($current, $newStageId, $actor, $lostReasonId, $probability);

            $updated = $this->model->find($record['id']);
            $this->audit->log('update', 'deal', $record['id'], array_diff_key($record, ['stage_id' => 1, 'probability' => 1, 'closed_at' => 1, 'lost_reason_id' => 1]), $updated);
            $db->transCommit();
        } catch (Throwable $e) {
            $db->transRollback();

            throw $e;
        }

        if ((int) $updated['owner_id'] !== (int) $record['owner_id']) {
            $this->notifyNewOwner($updated, $actor);
        }
    }

    /**
     * @throws ValidationFailedException
     */
    private function probability(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! ctype_digit((string) $value)) {
            throw new ValidationFailedException(['probability' => 'Probabilitas harus angka 0–100.']);
        }

        return (int) $value;
    }
}
