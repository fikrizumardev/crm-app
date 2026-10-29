<?php

namespace App\Models;

use App\Entities\User;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Model;

/**
 * Model dasar untuk entitas yang punya owner (PRD §3, §5).
 *
 * Cakupan data: admin → semua; manager → miliknya + milik sales bawahannya; sales → miliknya.
 * Semua query daftar/detail wajib lewat visibleTo() agar pembatasan terjadi di server.
 */
abstract class OwnedModel extends Model
{
    protected $returnType     = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;

    protected string $ownerField = 'owner_id';

    /**
     * Batasi query berikutnya ke data yang boleh dilihat user.
     *
     * @return $this
     */
    public function visibleTo(User $user): static
    {
        $ids = model(UserModel::class)->scopeIds($user);

        if ($ids !== null) {
            $this->whereIn($this->table . '.' . $this->ownerField, $ids);
        }

        return $this;
    }

    /**
     * Cari satu record yang boleh dilihat user; 404 bila tidak ada atau di luar cakupan.
     *
     * @return array<string, mixed>
     */
    public function findVisibleOrFail(int|string $id, User $user): array
    {
        $record = ctype_digit((string) $id) ? $this->visibleTo($user)->find((int) $id) : null;

        if ($record === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $record;
    }

    /**
     * @param array<string, mixed> $record
     */
    public function canView(User $user, array $record): bool
    {
        $ids = model(UserModel::class)->scopeIds($user);

        return $ids === null || in_array((int) $record[$this->ownerField], $ids, true);
    }

    /**
     * @param array<string, mixed> $record
     */
    public function canEdit(User $user, array $record): bool
    {
        return $this->canView($user, $record);
    }

    /**
     * Admin & manager boleh menghapus data dalam cakupannya; sales hanya miliknya yang belum terkunci.
     *
     * @param array<string, mixed> $record
     */
    public function canDelete(User $user, array $record): bool
    {
        if (! $this->canView($user, $record)) {
            return false;
        }

        if ($user->isSales()) {
            return (int) $record[$this->ownerField] === $user->id && ! $this->isLocked($record);
        }

        return true;
    }

    /**
     * Record yang sudah final (mis. deal Won, lead Dikonversi) tidak boleh dihapus sales.
     *
     * @param array<string, mixed> $record
     */
    public function isLocked(array $record): bool
    {
        return false;
    }

    /**
     * Terapkan urutan dari whitelist kolom; default bila tidak valid.
     *
     * @param array<string, string> $sortable kunci query string => kolom SQL
     *
     * @return $this
     */
    protected function applySort(array $sortable, ?string $sort, ?string $dir, string $defaultSort, string $defaultDir = 'desc'): static
    {
        $column    = $sortable[$sort ?? ''] ?? $sortable[$defaultSort];
        $direction = in_array(strtolower((string) $dir), ['asc', 'desc'], true) ? strtolower((string) $dir) : $defaultDir;

        if (! isset($sortable[$sort ?? ''])) {
            $direction = $defaultDir;
        }

        $this->orderBy($column, $direction)->orderBy($this->table . '.id', $direction);

        return $this;
    }

    /**
     * Filter rentang tanggal pada kolom datetime (format Y-m-d).
     *
     * @return $this
     */
    protected function applyDateRange(string $column, ?string $from, ?string $to): static
    {
        if ($from !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1) {
            $this->where($column . ' >=', $from . ' 00:00:00');
        }

        if ($to !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
            $this->where($column . ' <=', $to . ' 23:59:59');
        }

        return $this;
    }
}
