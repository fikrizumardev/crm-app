<?php

namespace App\Services;

use App\Models\AuditLogModel;
use CodeIgniter\Entity\Entity;
use CodeIgniter\HTTP\IncomingRequest;

/**
 * Mencatat siapa mengubah apa dan kapan ke tabel `audit_logs` (PRD §4 M9).
 */
class AuditLogger
{
    /**
     * Field yang tidak pernah dicatat nilainya.
     */
    private const HIDDEN_FIELDS = ['password', 'password_hash'];

    /**
     * Field teknis yang tidak perlu masuk diff.
     */
    private const IGNORED_FIELDS = ['created_at', 'updated_at', 'deleted_at'];

    public function __construct(private readonly AuditLogModel $model)
    {
    }

    /**
     * Catat satu aksi. Untuk aksi `update`, hanya field yang berubah yang disimpan;
     * bila tidak ada perubahan, tidak ada log yang ditulis.
     *
     * @param array<string, mixed>|Entity|null $old
     * @param array<string, mixed>|Entity|null $new
     */
    public function log(
        string $action,
        string $entity,
        int|string|null $entityId,
        array|Entity|null $old = null,
        array|Entity|null $new = null,
        ?int $userId = null,
    ): void {
        $old = $this->normalize($old);
        $new = $this->normalize($new);

        if ($old !== null && $new !== null) {
            [$old, $new] = $this->diff($old, $new);

            if ($old === [] && $new === []) {
                return;
            }
        }

        $this->model->insert([
            'user_id'    => $userId ?? $this->currentUserId(),
            'action'     => $action,
            'entity'     => $entity,
            'entity_id'  => $entityId === null ? null : (int) $entityId,
            'old_values' => $old === null ? null : json_encode($old, JSON_UNESCAPED_UNICODE),
            'new_values' => $new === null ? null : json_encode($new, JSON_UNESCAPED_UNICODE),
            'ip_address' => $this->ipAddress(),
        ]);
    }

    /**
     * @param array<string, mixed>|Entity|null $values
     *
     * @return array<string, mixed>|null
     */
    private function normalize(array|Entity|null $values): ?array
    {
        if ($values === null) {
            return null;
        }

        if ($values instanceof Entity) {
            $values = $values->toRawArray();
        }

        foreach (self::HIDDEN_FIELDS as $field) {
            if (array_key_exists($field, $values)) {
                $values[$field] = '***';
            }
        }

        return array_diff_key($values, array_flip(self::IGNORED_FIELDS));
    }

    /**
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function diff(array $old, array $new): array
    {
        $changedOld = [];
        $changedNew = [];

        foreach ($new as $key => $value) {
            if (! array_key_exists($key, $old)) {
                continue;
            }

            if ($this->isDifferent($old[$key], $value)) {
                $changedOld[$key] = $old[$key];
                $changedNew[$key] = $value;
            }
        }

        return [$changedOld, $changedNew];
    }

    private function isDifferent(mixed $old, mixed $new): bool
    {
        if ($old === null || $new === null || $old === '' || $new === '') {
            return ($old === null || $old === '') !== ($new === null || $new === '');
        }

        if (is_numeric($old) && is_numeric($new)) {
            return (float) $old !== (float) $new;
        }

        return (string) $old !== (string) $new;
    }

    private function currentUserId(): ?int
    {
        if (is_cli() && ENVIRONMENT !== 'testing') {
            return null;
        }

        $id = session('user_id');

        return $id === null ? null : (int) $id;
    }

    private function ipAddress(): ?string
    {
        $request = service('request');

        return $request instanceof IncomingRequest ? $request->getIPAddress() : null;
    }
}
