<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\ValidationFailedException;
use App\Models\ActivityModel;

/**
 * Validasi tautan polimorfik (lead/kontak/perusahaan/deal) untuk aktivitas & tugas.
 */
final class RelatedRecord
{
    /**
     * Record tertaut yang boleh dilihat user.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationFailedException
     */
    public static function findVisible(mixed $type, mixed $id, User $user): array
    {
        $type = (string) $type;
        $id   = ctype_digit((string) $id) ? (int) $id : 0;

        if (! isset(ActivityModel::RELATED[$type]) || $id <= 0) {
            throw new ValidationFailedException(['related_id' => 'Data tujuan tidak valid.']);
        }

        $record = model(ActivityModel::RELATED[$type]['model'])->visibleTo($user)->find($id);

        if ($record === null) {
            throw new ValidationFailedException(['related_id' => 'Data tujuan tidak ditemukan.']);
        }

        return $record;
    }

    public static function url(string $type, int|string $id): string
    {
        return site_url(ActivityModel::RELATED[$type]['path'] . '/' . $id);
    }
}
