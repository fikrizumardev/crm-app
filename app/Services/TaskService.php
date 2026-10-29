<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Models\TaskModel;
use App\Models\UserModel;
use CodeIgniter\I18n\Time;

class TaskService
{
    public function __construct(
        private readonly TaskModel $model,
        private readonly UserModel $users,
        private readonly AuditLogger $audit,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * Buat tugas mandiri atau tertaut ke record. Sales hanya untuk dirinya;
     * manager/admin boleh menugaskan user dalam cakupannya.
     *
     * @param array<string, mixed> $input title, description, due_at, priority, assigned_to, related_type, related_id
     *
     * @throws ValidationFailedException
     */
    public function create(array $input, User $actor): int
    {
        $relatedType = trim((string) ($input['related_type'] ?? ''));
        $relatedId   = null;

        if ($relatedType !== '') {
            RelatedRecord::findVisible($relatedType, $input['related_id'] ?? null, $actor);
            $relatedId = (int) $input['related_id'];
        }

        $data = [
            'title'        => trim((string) ($input['title'] ?? '')),
            'description'  => trim((string) ($input['description'] ?? '')) ?: null,
            'due_at'       => parse_datetime_input($input['due_at'] ?? null),
            'priority'     => (string) ($input['priority'] ?? 'medium'),
            'status'       => TaskModel::STATUS_OPEN,
            'related_type' => $relatedType === '' ? null : $relatedType,
            'related_id'   => $relatedId,
            'assigned_to'  => $this->assignee($input['assigned_to'] ?? null, $actor),
            'created_by'   => $actor->id,
        ];

        $id = $this->model->insert($data);

        if ($id === false) {
            throw new ValidationFailedException($this->model->errors());
        }

        $this->audit->log('create', 'task', $id, null, $data);
        $this->notifications->taskAssigned($data, $actor);

        return (int) $id;
    }

    /**
     * Tandai selesai.
     *
     * @param array<string, mixed> $task
     *
     * @throws BusinessRuleException
     */
    public function complete(array $task, User $actor): void
    {
        if (! $this->model->canEdit($actor, $task)) {
            throw new BusinessRuleException('Anda tidak berhak menyelesaikan tugas ini.');
        }

        if ($task['status'] === TaskModel::STATUS_DONE) {
            return;
        }

        $changes = ['status' => TaskModel::STATUS_DONE, 'completed_at' => Time::now()->toDateTimeString()];
        $this->model->skipValidation()->update($task['id'], $changes);
        $this->model->skipValidation(false);
        $this->audit->log('complete', 'task', $task['id'], ['status' => $task['status']], $changes);
    }

    /**
     * Buka kembali tugas yang sudah selesai.
     *
     * @param array<string, mixed> $task
     */
    public function reopen(array $task, User $actor): void
    {
        if (! $this->model->canEdit($actor, $task)) {
            throw new BusinessRuleException('Anda tidak berhak mengubah tugas ini.');
        }

        $changes = ['status' => TaskModel::STATUS_OPEN, 'completed_at' => null];
        $this->model->skipValidation()->update($task['id'], $changes);
        $this->model->skipValidation(false);
        $this->audit->log('reopen', 'task', $task['id'], ['status' => $task['status']], $changes);
    }

    /**
     * Pembuat tugas juga boleh menghapus, selain aturan umum OwnedModel.
     *
     * @param array<string, mixed> $task
     */
    public function canDelete(array $task, User $actor): bool
    {
        return (int) $task['created_by'] === $actor->id || $this->model->canDelete($actor, $task);
    }

    /**
     * @param array<string, mixed> $task
     */
    public function delete(array $task, User $actor): void
    {
        if (! $this->canDelete($task, $actor)) {
            throw new BusinessRuleException('Anda tidak berhak menghapus tugas ini.');
        }

        $this->model->delete($task['id']);
        $this->audit->log('delete', 'task', $task['id'], $task, null);
    }

    /**
     * @throws ValidationFailedException
     */
    private function assignee(mixed $requested, User $actor): int
    {
        if ($actor->isSales() || $requested === null || $requested === '') {
            return $actor->id;
        }

        if (! array_key_exists((int) $requested, $this->users->ownerOptions($actor))) {
            throw new ValidationFailedException(['assigned_to' => 'Pilih user dalam tim Anda.']);
        }

        return (int) $requested;
    }
}
