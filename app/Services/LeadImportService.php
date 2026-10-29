<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Libraries\Csv;
use App\Models\LeadModel;
use App\Models\LeadSourceModel;
use App\Models\UserModel;

/**
 * Impor lead dari CSV dengan validasi per baris (PRD §4 M3, P1).
 */
class LeadImportService
{
    public const MAX_ROWS = 1000;

    /**
     * Kolom template beserta contoh isi.
     */
    public const COLUMNS = [
        'nama'           => 'Rahmat Hidayat',
        'perusahaan'     => 'PT Cahaya Abadi Logistik',
        'jabatan'        => 'Manajer Operasional',
        'no_hp'          => '0812-3456-7899',
        'email'          => 'rahmat@cahayaabadi.co.id',
        'sumber'         => 'Pameran',
        'status'         => 'Baru',
        'catatan'        => 'Tertarik modul gudang, minta demo bulan depan.',
        'owner_username' => '',
    ];

    public function __construct(
        private readonly LeadService $leads,
        private readonly LeadSourceModel $sources,
        private readonly UserModel $users,
        private readonly AuditLogger $audit,
    ) {
    }

    public function templateCsv(): string
    {
        return Csv::build(array_keys(self::COLUMNS), [array_values(self::COLUMNS)]);
    }

    /**
     * @return array{imported: int, failed: list<array{line: int, name: string, errors: list<string>}>}
     *
     * @throws BusinessRuleException
     */
    public function import(string $path, User $actor): array
    {
        if (! in_array('nama', Csv::headers($path), true)) {
            throw new BusinessRuleException('Kolom "nama" tidak ditemukan. Gunakan template CSV yang disediakan.');
        }

        $rows       = Csv::read($path, self::MAX_ROWS);
        $sourceIds  = $this->sources->options();
        $ownerIds   = $actor->isSales() ? [] : $this->ownerIdsByUsername($actor);
        $seenPhones = [];
        $seenEmails = [];
        $imported   = 0;
        $failed     = [];

        foreach ($rows as $row) {
            $data   = $row['data'];
            $errors = [];
            $input  = [
                'name'         => $data['nama'] ?? '',
                'company_name' => $data['perusahaan'] ?? '',
                'job_title'    => $data['jabatan'] ?? '',
                'phone'        => $data['no_hp'] ?? '',
                'email'        => $data['email'] ?? '',
                'notes'        => $data['catatan'] ?? '',
                'status'       => $this->status($data['status'] ?? '', $errors),
                'source_id'    => $this->source($data['sumber'] ?? '', $sourceIds, $errors),
                'owner_id'     => $this->owner($data['owner_username'] ?? '', $ownerIds, $errors),
            ];

            $phone = normalize_phone($input['phone']);
            $email = strtolower(trim($input['email']));

            if (($phone !== null && isset($seenPhones[$phone])) || ($email !== '' && isset($seenEmails[$email]))) {
                $errors[] = 'Duplikat dengan baris lain di file ini.';
            } elseif ($errors === [] && ($dup = $this->leads->duplicates($input)) !== []) {
                $errors[] = sprintf('Duplikat: %s sudah dipakai %s "%s".', $dup[0]['match'], strtolower($dup[0]['type']), $dup[0]['name']);
            }

            if ($errors === []) {
                try {
                    $this->leads->create($input, $actor);
                    $imported++;

                    if ($phone !== null) {
                        $seenPhones[$phone] = true;
                    }

                    if ($email !== '') {
                        $seenEmails[$email] = true;
                    }

                    continue;
                } catch (ValidationFailedException $e) {
                    $errors = array_values($e->getErrors());
                }
            }

            $failed[] = ['line' => $row['line'], 'name' => $input['name'], 'errors' => $errors];
        }

        $this->audit->log('import', 'lead', null, null, ['imported' => $imported, 'failed' => count($failed)]);

        return ['imported' => $imported, 'failed' => $failed];
    }

    /**
     * @param list<string> $errors
     */
    private function status(string $value, array &$errors): string
    {
        $value = mb_strtolower(trim($value));

        if ($value === '') {
            return LeadModel::STATUS_NEW;
        }

        foreach (LeadModel::STATUSES as $code => $label) {
            if ($value === $code || $value === mb_strtolower($label)) {
                if ($code === LeadModel::STATUS_CONVERTED) {
                    $errors[] = 'Status Dikonversi tidak bisa diimpor.';
                }

                return $code;
            }
        }

        $errors[] = sprintf('Status "%s" tidak dikenal.', $value);

        return LeadModel::STATUS_NEW;
    }

    /**
     * @param array<int, string> $sourceIds
     * @param list<string>       $errors
     */
    private function source(string $value, array $sourceIds, array &$errors): ?int
    {
        if (trim($value) === '') {
            return null;
        }

        foreach ($sourceIds as $id => $name) {
            if (mb_strtolower($name) === mb_strtolower(trim($value))) {
                return $id;
            }
        }

        $errors[] = sprintf('Sumber "%s" tidak dikenal.', $value);

        return null;
    }

    /**
     * @param array<string, int> $ownerIds
     * @param list<string>       $errors
     */
    private function owner(string $username, array $ownerIds, array &$errors): ?int
    {
        $username = strtolower(trim($username));

        if ($username === '' || $ownerIds === []) {
            return null;
        }

        if (! isset($ownerIds[$username])) {
            $errors[] = sprintf('Owner "%s" tidak ditemukan atau di luar tim Anda.', $username);

            return null;
        }

        return $ownerIds[$username];
    }

    /**
     * @return array<string, int>
     */
    private function ownerIdsByUsername(User $actor): array
    {
        $result = [];

        foreach ($this->users->assignableOwners($actor) as $user) {
            $result[$user->username] = $user->id;
        }

        return $result;
    }
}
