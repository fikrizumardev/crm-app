<?php

namespace App\Services;

use App\Entities\User;

class CompanyService extends RecordService
{
    protected function entity(): string
    {
        return 'company';
    }

    protected function entityLabel(): string
    {
        return 'perusahaan';
    }

    protected function path(): string
    {
        return 'companies';
    }

    protected function prepare(array $input, User $actor, ?array $existing): array
    {
        $npwp      = $this->text($input['npwp'] ?? null);
        $employees = $this->text($input['employee_count'] ?? null);

        return [
            'name'           => $this->text($input['name'] ?? null),
            'industry_id'    => $this->id($input['industry_id'] ?? null),
            // NPWP disimpan sebagai angka saja; tanda baca hanya untuk tampilan.
            'npwp'           => $npwp === null ? null : (preg_match('/^[0-9.\-\s]+$/', $npwp) === 1 ? preg_replace('/\D+/', '', $npwp) : $npwp),
            'phone'          => normalize_phone($input['phone'] ?? null),
            'website'        => $this->website($input['website'] ?? null),
            'address'        => $this->text($input['address'] ?? null),
            'city'           => $this->text($input['city'] ?? null),
            'province'       => $this->text($input['province'] ?? null),
            'employee_count' => $employees === null ? null : str_replace('.', '', $employees),
        ];
    }

    /**
     * Tambahkan skema https:// bila user hanya menulis domain.
     */
    private function website(mixed $value): ?string
    {
        $value = $this->text($value);

        if ($value === null) {
            return null;
        }

        return preg_match('#^https?://#i', $value) === 1 ? $value : 'https://' . $value;
    }
}
