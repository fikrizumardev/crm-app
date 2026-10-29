<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Models\ActivityModel;
use CodeIgniter\I18n\Time;

class ActivityService
{
    public function __construct(
        private readonly ActivityModel $model,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Catat aktivitas pada record yang boleh dilihat user.
     *
     * @param array<string, mixed> $input type, subject, description, related_type, related_id, activity_at
     *
     * @throws ValidationFailedException
     */
    public function create(array $input, User $actor): int
    {
        RelatedRecord::findVisible($input['related_type'] ?? null, $input['related_id'] ?? null, $actor);

        $data = [
            'type'         => (string) ($input['type'] ?? ''),
            'subject'      => trim((string) ($input['subject'] ?? '')),
            'description'  => trim((string) ($input['description'] ?? '')) ?: null,
            'related_type' => (string) $input['related_type'],
            'related_id'   => (int) $input['related_id'],
            'activity_at'  => parse_datetime_input($input['activity_at'] ?? null) ?? Time::now()->toDateTimeString(),
            'user_id'      => $actor->id,
        ];

        $id = $this->model->insert($data);

        if ($id === false) {
            throw new ValidationFailedException($this->model->errors());
        }

        $this->audit->log('create', 'activity', $id, null, $data);

        return (int) $id;
    }

    /**
     * Hanya pencatat aktivitas atau admin yang boleh menghapus.
     *
     * @param array<string, mixed> $activity
     */
    public function canDelete(array $activity, User $actor): bool
    {
        return $actor->isAdmin() || (int) $activity['user_id'] === $actor->id;
    }

    /**
     * @param array<string, mixed> $activity
     *
     * @throws BusinessRuleException
     */
    public function delete(array $activity, User $actor): void
    {
        if (! $this->canDelete($activity, $actor)) {
            throw new BusinessRuleException('Hanya pencatat aktivitas atau admin yang bisa menghapusnya.');
        }

        $this->model->delete($activity['id']);
        $this->audit->log('delete', 'activity', $activity['id'], $activity, null);
    }
}
