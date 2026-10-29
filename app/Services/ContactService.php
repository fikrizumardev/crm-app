<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\ValidationFailedException;
use App\Models\CompanyModel;
use App\Models\ContactModel;

class ContactService extends RecordService
{
    protected function entity(): string
    {
        return 'contact';
    }

    protected function entityLabel(): string
    {
        return 'kontak';
    }

    protected function path(): string
    {
        return 'contacts';
    }

    protected function recordName(array $record): string
    {
        return ContactModel::fullName($record);
    }

    protected function prepare(array $input, User $actor, ?array $existing): array
    {
        $companyId = $this->id($input['company_id'] ?? null);

        // Perusahaan harus berada dalam cakupan data user (kecuali tidak diubah dari nilai lama).
        if ($companyId !== null && $companyId !== (int) ($existing['company_id'] ?? 0)
            && model(CompanyModel::class)->visibleTo($actor)->find($companyId) === null) {
            throw new ValidationFailedException(['company_id' => 'Perusahaan tidak ditemukan.']);
        }

        return [
            'company_id' => $companyId,
            'first_name' => $this->text($input['first_name'] ?? null),
            'last_name'  => $this->text($input['last_name'] ?? null),
            'job_title'  => $this->text($input['job_title'] ?? null),
            'phone'      => normalize_phone($input['phone'] ?? null),
            'whatsapp'   => normalize_phone($input['whatsapp'] ?? null),
            'email'      => $this->email($input['email'] ?? null),
            'birth_date' => $this->text($input['birth_date'] ?? null),
        ];
    }
}
