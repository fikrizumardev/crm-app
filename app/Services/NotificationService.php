<?php

namespace App\Services;

use App\Entities\User;
use App\Models\NotificationModel;
use App\Models\TaskModel;
use CodeIgniter\I18n\Time;

/**
 * Notifikasi dalam aplikasi (ikon lonceng) — PRD §4 M7.
 */
class NotificationService
{
    public const TYPE_TASK_ASSIGNED = 'task_assigned';
    public const TYPE_TASK_DUE      = 'task_due';
    public const TYPE_REASSIGNED    = 'reassigned';

    public function __construct(private readonly NotificationModel $model)
    {
    }

    public function notify(int $userId, string $type, string $message, ?string $url = null): void
    {
        $this->model->insert([
            'user_id' => $userId,
            'type'    => $type,
            'message' => mb_substr($message, 0, 255),
            'url'     => $url,
        ]);
    }

    /**
     * Tugas ditugaskan ke orang lain.
     *
     * @param array<string, mixed> $task
     */
    public function taskAssigned(array $task, User $actor): void
    {
        if ((int) $task['assigned_to'] === $actor->id) {
            return;
        }

        $this->notify(
            (int) $task['assigned_to'],
            self::TYPE_TASK_ASSIGNED,
            sprintf('%s memberi Anda tugas: %s (jatuh tempo %s)', $actor->name, $task['title'], tanggal_id($task['due_at'], true)),
            site_url('tasks?tab=' . (str_starts_with((string) $task['due_at'], date('Y-m-d')) ? 'today' : 'week')),
        );
    }

    /**
     * Owner sebuah record diganti ke user lain.
     */
    public function reassigned(string $entityLabel, string $recordName, string $url, int $newOwnerId, User $actor): void
    {
        if ($newOwnerId === $actor->id) {
            return;
        }

        $this->notify(
            $newOwnerId,
            self::TYPE_REASSIGNED,
            sprintf('%s mengalihkan %s "%s" kepada Anda.', $actor->name, $entityLabel, $recordName),
            $url,
        );
    }

    /**
     * Ringkasan tugas jatuh tempo hari ini; dibuat sekali per hari saat user login (tanpa cron).
     */
    public function dueTodayDigest(User $user): void
    {
        if ($this->model->existsToday($user->id, self::TYPE_TASK_DUE)) {
            return;
        }

        $count = model(TaskModel::class)
            ->where('assigned_to', $user->id)
            ->where('status', TaskModel::STATUS_OPEN)
            ->where('due_at >=', Time::today()->toDateTimeString())
            ->where('due_at <', Time::tomorrow()->toDateTimeString())
            ->countAllResults();

        if ($count === 0) {
            return;
        }

        $this->notify(
            $user->id,
            self::TYPE_TASK_DUE,
            sprintf('Anda punya %d tugas yang jatuh tempo hari ini.', $count),
            site_url('tasks?tab=today'),
        );
    }
}
