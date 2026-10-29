<?php

namespace App\Models;

use App\Entities\User;
use CodeIgniter\I18n\Time;

/**
 * Tugas/follow-up. Cakupan data berdasarkan `assigned_to` (PRD §4 M7).
 */
class TaskModel extends OwnedModel
{
    public const STATUS_OPEN = 'open';
    public const STATUS_DONE = 'done';

    public const PRIORITIES = [
        'low'    => ['label' => 'Rendah', 'color' => 'gray'],
        'medium' => ['label' => 'Sedang', 'color' => 'yellow'],
        'high'   => ['label' => 'Tinggi', 'color' => 'red'],
    ];

    /**
     * Tab daftar tugas.
     */
    public const TABS = [
        'today'   => 'Hari ini',
        'overdue' => 'Terlambat',
        'week'    => 'Minggu ini',
        'done'    => 'Selesai',
    ];

    protected $table         = 'tasks';
    protected $primaryKey    = 'id';
    protected string $ownerField = 'assigned_to';
    protected $allowedFields = [
        'title',
        'description',
        'due_at',
        'priority',
        'status',
        'completed_at',
        'related_type',
        'related_id',
        'assigned_to',
        'created_by',
    ];
    protected $validationRules = [
        'title'        => ['label' => 'Judul tugas', 'rules' => 'required|max_length[200]'],
        'description'  => ['label' => 'Keterangan', 'rules' => 'permit_empty|max_length[5000]'],
        'due_at'       => ['label' => 'Jatuh tempo', 'rules' => 'required|valid_date[Y-m-d H:i:s]'],
        'priority'     => ['label' => 'Prioritas', 'rules' => 'required|in_list[low,medium,high]'],
        'status'       => ['label' => 'Status', 'rules' => 'required|in_list[open,done]'],
        'related_type' => ['label' => 'Tautan', 'rules' => 'permit_empty|in_list[lead,contact,company,deal]'],
        'related_id'   => ['label' => 'Tautan', 'rules' => 'permit_empty|is_natural_no_zero'],
        'assigned_to'  => ['label' => 'Ditugaskan ke', 'rules' => 'required|is_natural_no_zero|is_not_unique[users.id]'],
    ];

    /**
     * @return $this
     */
    private function withNames(): static
    {
        return $this->select('tasks.*, assignee.name AS assignee_name, creator.name AS creator_name')
            ->join('users AS assignee', 'assignee.id = tasks.assigned_to', 'left')
            ->join('users AS creator', 'creator.id = tasks.created_by', 'left');
    }

    /**
     * Terapkan kondisi tab.
     *
     * @return $this
     */
    private function applyTab(string $tab): static
    {
        $today    = Time::today()->toDateTimeString();
        $tomorrow = Time::tomorrow()->toDateTimeString();
        // Minggu berjalan berakhir Minggu 23:59 (Senin–Minggu).
        $weekEnd = Time::today()->addDays(8 - (int) Time::today()->format('N'))->toDateTimeString();

        return match ($tab) {
            'today'   => $this->where('tasks.status', self::STATUS_OPEN)->where('tasks.due_at >=', $today)->where('tasks.due_at <', $tomorrow),
            'overdue' => $this->where('tasks.status', self::STATUS_OPEN)->where('tasks.due_at <', $today),
            'week'    => $this->where('tasks.status', self::STATUS_OPEN)->where('tasks.due_at >=', $today)->where('tasks.due_at <', $weekEnd),
            default   => $this->where('tasks.status', self::STATUS_DONE),
        };
    }

    /**
     * Daftar tugas untuk tab tertentu.
     *
     * @return $this
     */
    public function forTab(string $tab, User $user, ?int $assigneeId = null): static
    {
        $this->withNames()->visibleTo($user)->applyTab($tab);

        if ($assigneeId !== null) {
            $this->where('tasks.assigned_to', $assigneeId);
        }

        return $tab === 'done'
            ? $this->orderBy('tasks.completed_at', 'DESC')
            : $this->orderBy('tasks.due_at', 'ASC')->orderBy("FIELD(tasks.priority, 'high', 'medium', 'low')", '', false);
    }

    /**
     * Jumlah tugas per tab.
     *
     * @return array<string, int>
     */
    public function tabCounts(User $user, ?int $assigneeId = null): array
    {
        $counts = [];

        foreach (array_keys(self::TABS) as $tab) {
            $this->visibleTo($user)->applyTab($tab);

            if ($assigneeId !== null) {
                $this->where('tasks.assigned_to', $assigneeId);
            }

            $counts[$tab] = $this->countAllResults();
        }

        return $counts;
    }

    /**
     * Tugas terbuka yang tertaut ke satu record.
     *
     * @return list<array<string, mixed>>
     */
    public function forRecord(string $type, int $id, User $user): array
    {
        return $this->withNames()->visibleTo($user)
            ->where('tasks.related_type', $type)
            ->where('tasks.related_id', $id)
            ->orderBy('tasks.status', 'DESC')
            ->orderBy('tasks.due_at', 'ASC')
            ->findAll(50);
    }

    /**
     * @param array<string, mixed> $task
     */
    public static function isOverdue(array $task): bool
    {
        return $task['status'] === self::STATUS_OPEN && $task['due_at'] < Time::today()->toDateTimeString();
    }
}
