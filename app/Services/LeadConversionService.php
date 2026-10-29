<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Models\CompanyModel;
use App\Models\ContactModel;
use App\Models\DealModel;
use App\Models\DealStageHistoryModel;
use App\Models\LeadModel;
use App\Models\PipelineStageModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use CodeIgniter\Model;
use Throwable;

/**
 * Konversi lead menjadi Perusahaan + Kontak + (opsional) Deal dalam satu transaksi (PRD §4 M3).
 */
class LeadConversionService
{
    public function __construct(
        private readonly LeadModel $leads,
        private readonly CompanyModel $companies,
        private readonly ContactModel $contacts,
        private readonly DealModel $deals,
        private readonly DealStageHistoryModel $history,
        private readonly PipelineStageModel $stages,
        private readonly AuditLogger $audit,
        private readonly BaseConnection $db,
    ) {
    }

    public static function create(): self
    {
        return new self(
            model(LeadModel::class),
            model(CompanyModel::class),
            model(ContactModel::class),
            model(DealModel::class),
            model(DealStageHistoryModel::class),
            model(PipelineStageModel::class),
            service('auditLogger'),
            db_connect(),
        );
    }

    /**
     * Field input (nama datar):
     * - company_mode `new|existing`, company_id, company_name, company_industry_id, company_city, company_province
     * - contact_first_name, contact_last_name, contact_job_title, contact_phone, contact_email
     * - create_deal, deal_title, deal_amount, deal_stage_id, deal_expected_close_date
     *
     * @param array<string, mixed> $lead
     * @param array<string, mixed> $input
     *
     * @return array{company_id: int, contact_id: int, deal_id: int|null}
     *
     * @throws BusinessRuleException|ValidationFailedException
     */
    public function convert(array $lead, array $input, User $actor): array
    {
        if ($lead['status'] === LeadModel::STATUS_CONVERTED) {
            throw new BusinessRuleException('Lead ini sudah dikonversi sebelumnya.');
        }

        $ownerId = (int) $lead['owner_id'];
        $now     = Time::now()->toDateTimeString();

        $this->db->transBegin();

        try {
            $companyId = $this->resolveCompany($input, $actor, $ownerId);
            $contactId = $this->createContact($input, $companyId, $ownerId);
            $dealId    = empty($input['create_deal']) ? null : $this->createDeal($input, $companyId, $contactId, $ownerId, $actor, $now);

            $converted = [
                'status'               => LeadModel::STATUS_CONVERTED,
                'converted_company_id' => $companyId,
                'converted_contact_id' => $contactId,
                'converted_deal_id'    => $dealId,
                'converted_at'         => $now,
            ];

            if (! $this->leads->update($lead['id'], $converted)) {
                throw new ValidationFailedException($this->leads->errors());
            }

            $this->audit->log('convert', 'lead', $lead['id'], ['status' => $lead['status']], $converted);

            $this->db->transCommit();
        } catch (Throwable $e) {
            $this->db->transRollback();

            throw $e;
        }

        return ['company_id' => $companyId, 'contact_id' => $contactId, 'deal_id' => $dealId];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function resolveCompany(array $input, User $actor, int $ownerId): int
    {
        if (($input['company_mode'] ?? 'new') === 'existing') {
            $companyId = (int) ($input['company_id'] ?? 0);

            if ($companyId <= 0 || $this->companies->visibleTo($actor)->find($companyId) === null) {
                throw new ValidationFailedException(['company_id' => 'Pilih perusahaan yang sudah terdaftar.']);
            }

            return $companyId;
        }

        return $this->insert($this->companies, 'company', 'company_', [
            'name'        => $this->text($input['company_name'] ?? null),
            'industry_id' => $this->id($input['company_industry_id'] ?? null),
            'city'        => $this->text($input['company_city'] ?? null),
            'province'    => $this->text($input['company_province'] ?? null),
            'owner_id'    => $ownerId,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function createContact(array $input, int $companyId, int $ownerId): int
    {
        $phone = normalize_phone($input['contact_phone'] ?? null);
        $email = $this->text($input['contact_email'] ?? null);

        return $this->insert($this->contacts, 'contact', 'contact_', [
            'company_id' => $companyId,
            'first_name' => $this->text($input['contact_first_name'] ?? null),
            'last_name'  => $this->text($input['contact_last_name'] ?? null),
            'job_title'  => $this->text($input['contact_job_title'] ?? null),
            'phone'      => $phone,
            'whatsapp'   => $phone,
            'email'      => $email === null ? null : strtolower($email),
            'owner_id'   => $ownerId,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function createDeal(array $input, int $companyId, int $contactId, int $ownerId, User $actor, string $now): int
    {
        $stageId = $this->id($input['deal_stage_id'] ?? null) ?? (int) ($this->stages->openStages()[0]['id'] ?? 0);
        $stage   = $this->stages->find($stageId);

        if ($stage === null || (int) $stage['is_won'] === 1 || (int) $stage['is_lost'] === 1) {
            throw new ValidationFailedException(['deal_stage_id' => 'Pilih tahap pipeline yang masih terbuka.']);
        }

        $dealId = $this->insert($this->deals, 'deal', 'deal_', [
            'title'               => $this->text($input['deal_title'] ?? null),
            'company_id'          => $companyId,
            'contact_id'          => $contactId,
            'stage_id'            => $stageId,
            'amount'              => parse_rupiah($input['deal_amount'] ?? null),
            'probability'         => (int) $stage['probability'],
            'expected_close_date' => $this->text($input['deal_expected_close_date'] ?? null),
            'owner_id'            => $ownerId,
        ]);

        $this->history->insert([
            'deal_id'       => $dealId,
            'from_stage_id' => null,
            'to_stage_id'   => $stageId,
            'changed_by'    => $actor->id,
            'changed_at'    => $now,
        ]);

        return $dealId;
    }

    /**
     * Simpan lewat model (validasi model berlaku); error dipetakan ke nama field form ber-prefix.
     *
     * @param array<string, mixed> $data
     */
    private function insert(Model $model, string $entity, string $prefix, array $data): int
    {
        $id = $model->insert($data);

        if ($id === false) {
            $errors = [];

            foreach ($model->errors() as $field => $message) {
                $errors[$prefix . $field] = $message;
            }

            throw new ValidationFailedException($errors);
        }

        $this->audit->log('create', $entity, $id, null, $model->find($id));

        return (int) $id;
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function id(mixed $value): ?int
    {
        return ctype_digit(trim((string) $value)) && (int) $value > 0 ? (int) $value : null;
    }
}
