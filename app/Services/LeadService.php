<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\ValidationFailedException;
use App\Models\LeadModel;

class LeadService extends RecordService
{
    protected function entity(): string
    {
        return 'lead';
    }

    protected function entityLabel(): string
    {
        return 'lead';
    }

    protected function path(): string
    {
        return 'leads';
    }

    protected function prepare(array $input, User $actor, ?array $existing): array
    {
        $status = (string) ($input['status'] ?? LeadModel::STATUS_NEW);

        if ($existing !== null && $existing['status'] === LeadModel::STATUS_CONVERTED) {
            // Lead yang sudah dikonversi statusnya tetap.
            $status = LeadModel::STATUS_CONVERTED;
        } elseif ($status === LeadModel::STATUS_CONVERTED) {
            throw new ValidationFailedException(['status' => 'Status Dikonversi hanya bisa diset lewat tombol Konversi.']);
        }

        return [
            'name'         => $this->text($input['name'] ?? null),
            'company_name' => $this->text($input['company_name'] ?? null),
            'job_title'    => $this->text($input['job_title'] ?? null),
            'phone'        => normalize_phone($input['phone'] ?? null),
            'email'        => $this->email($input['email'] ?? null),
            'source_id'    => $this->id($input['source_id'] ?? null),
            'status'       => $status,
            'notes'        => $this->text($input['notes'] ?? null),
        ];
    }

    /**
     * Data lain (lead/kontak) yang memakai no. HP atau email yang sama.
     *
     * @param array<string, mixed> $input
     *
     * @return list<array<string, mixed>>
     */
    public function duplicates(array $input, ?int $excludeLeadId = null): array
    {
        /** @var LeadModel $model */
        $model = $this->model;

        return $model->findDuplicates(
            normalize_phone($input['phone'] ?? null),
            $this->email($input['email'] ?? null),
            $excludeLeadId,
        );
    }
}
