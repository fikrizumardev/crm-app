<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\ValidationFailedException;
use App\Models\OwnedModel;
use App\Models\UserModel;

/**
 * Logika CRUD bersama untuk entitas ber-owner: normalisasi input, validasi owner,
 * simpan lewat Model (validasi), dan catat ke log audit.
 */
abstract class RecordService
{
    public function __construct(
        protected readonly OwnedModel $model,
        protected readonly AuditLogger $audit,
        protected readonly UserModel $users,
    ) {
    }

    /**
     * Nama entitas untuk log audit, mis. "lead".
     */
    abstract protected function entity(): string;

    /**
     * Nama entitas untuk pesan ke user, mis. "perusahaan".
     */
    abstract protected function entityLabel(): string;

    /**
     * Path URL modul, mis. "companies".
     */
    abstract protected function path(): string;

    /**
     * Nama record untuk pesan/notifikasi.
     *
     * @param array<string, mixed> $record
     */
    protected function recordName(array $record): string
    {
        return (string) ($record['name'] ?? '#' . $record['id']);
    }

    /**
     * Ubah input form menjadi data kolom tabel (tanpa owner_id).
     *
     * @param array<string, mixed>      $input
     * @param array<string, mixed>|null $existing record lama saat update
     *
     * @return array<string, mixed>
     */
    abstract protected function prepare(array $input, User $actor, ?array $existing): array;

    /**
     * @param array<string, mixed> $input
     *
     * @throws ValidationFailedException
     */
    public function create(array $input, User $actor): int
    {
        $data             = $this->prepare($input, $actor, null);
        $data['owner_id'] = $this->resolveOwner($input['owner_id'] ?? null, $actor, null);

        $id = $this->model->insert($data);

        if ($id === false) {
            throw new ValidationFailedException($this->model->errors());
        }

        $this->audit->log('create', $this->entity(), $id, null, $this->model->find($id));

        return (int) $id;
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $input
     *
     * @throws ValidationFailedException
     */
    public function update(array $record, array $input, User $actor): void
    {
        $data             = $this->prepare($input, $actor, $record);
        $data['owner_id'] = $this->resolveOwner($input['owner_id'] ?? null, $actor, (int) $record['owner_id']);

        if (! $this->model->update($record['id'], $data)) {
            throw new ValidationFailedException($this->model->errors());
        }

        $updated = $this->model->find($record['id']);

        // Diff hanya mencakup kolom tabel; kolom hasil join pada $record diabaikan AuditLogger.
        $this->audit->log('update', $this->entity(), $record['id'], $record, $updated);

        if ((int) $data['owner_id'] !== (int) $record['owner_id']) {
            $this->notifyNewOwner($updated, $actor);
        }
    }

    /**
     * Beri tahu owner baru bahwa record dialihkan kepadanya.
     *
     * @param array<string, mixed> $record
     */
    protected function notifyNewOwner(array $record, User $actor): void
    {
        service('notifications')->reassigned(
            $this->entityLabel(),
            $this->recordName($record),
            site_url($this->path() . '/' . $record['id']),
            (int) $record['owner_id'],
            $actor,
        );
    }

    /**
     * Soft delete.
     *
     * @param array<string, mixed> $record
     */
    public function delete(array $record, User $actor): void
    {
        $this->model->delete($record['id']);
        $this->audit->log('delete', $this->entity(), $record['id'], $record, null);
    }

    /**
     * Owner yang dipakai: sales selalu dirinya (atau owner lama), admin/manager boleh memilih
     * user aktif dalam cakupannya.
     *
     * @throws ValidationFailedException
     */
    protected function resolveOwner(mixed $requested, User $actor, ?int $current): int
    {
        if ($actor->isSales() || $requested === null || $requested === '') {
            return $current ?? $actor->id;
        }

        $requested = (int) $requested;

        if ($requested === $current) {
            return $requested;
        }

        if (! array_key_exists($requested, $this->users->ownerOptions($actor))) {
            throw new ValidationFailedException(['owner_id' => 'Owner tidak valid atau di luar tim Anda.']);
        }

        return $requested;
    }

    /**
     * Teks bersih atau null bila kosong.
     */
    protected function text(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function id(mixed $value): ?int
    {
        return ctype_digit(trim((string) $value)) && (int) $value > 0 ? (int) $value : null;
    }

    protected function email(mixed $value): ?string
    {
        $value = $this->text($value);

        return $value === null ? null : strtolower($value);
    }
}
